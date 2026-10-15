#!/usr/bin/env bash
#
# Jedu production installer.
#
# Brings up the whole production stack on one host from images published to
# Nexus: Caddy (TLS + routing) in front of the API, the storefront and the admin
# panel, plus the API worker (Horizon + scheduler) and the three datastores the
# API needs (PostgreSQL with pgroonga, Redis, Typesense).
#
# It writes ./.env, pulls the images, starts the stack, waits for the API to
# report healthy and points the deployment at the Typesense search engine.
#
# Re-running is safe: values already in ./.env are kept, so secrets are never
# rotated behind the database's back. Use --reset-env to re-answer everything,
# and only against a fresh database.
#
#   ./install.sh --help
#
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
cd "$SCRIPT_DIR"

readonly ENV_FILE="$SCRIPT_DIR/.env"
readonly ENV_TEMPLATE="$SCRIPT_DIR/.env.example"
readonly COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"
readonly COMPOSE_PROJECT="jedu-production"
readonly HEALTH_TIMEOUT=600

# Where set_env applies its edits. The writing step points this at a scratch copy
# and moves it over .env in one rename, so a `docker compose` running at the same
# time can never read a half-updated file.
ENV_WORK="$ENV_FILE"

# ── Output ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

log_info() { printf '%b\n' "${BLUE}[INFO]${NC} $1"; }
log_success() { printf '%b\n' "${GREEN}[ OK ]${NC} $1"; }
log_warn() { printf '%b\n' "${YELLOW}[WARN]${NC} $1"; }
log_error() { printf '%b\n' "${RED}[FAIL]${NC} $1" >&2; }
log_step() { printf '\n%b\n' "${BOLD}==> $1${NC}"; }

usage() {
    cat <<'EOF'
Usage: ./install.sh [OPTIONS]

Runs in the directory it lives in and writes ./.env (mode 600). Re-running keeps
every value already present in .env, secrets included.

Configuration:
  --domain <suffix>        Derive all three hostnames from one suffix, e.g.
                           `--domain jedu.ir` gives api/shop/admin.jedu.ir
  --api-domain <host>      Public API hostname
  --shop-domain <host>     Public storefront hostname
  --admin-domain <host>    Public admin hostname
  --email <address>        Let's Encrypt account email (required unless --no-tls)

Images:
  --api-tag <tag>          Tag for the API image (default: latest)
  --shop-tag <tag>         Tag for the storefront image (default: 1.0.0)
  --admin-tag <tag>        Tag for the admin image (default: latest)
  --admin-image <ref>      Full admin image reference, overrides --admin-tag
  --skip-admin             Do not pull or start the admin panel; the admin
                           hostname answers 502 until you install it

Behaviour:
  -y, --yes                Never prompt; fail if a required value is missing
  --no-tls                 Serve plain HTTP on :80 for a host-level proxy that
                           terminates TLS itself
  --registry-user <user>   Log in to the registry before pulling
  --registry-password <pw> Password for --registry-user (or set REGISTRY_PASSWORD)
  --no-login               Never attempt `docker login`
  --no-pull                Use the images already on this host
  --rebuild-search         Index products into Typesense after the stack is up
  --reset-env              Ignore the existing .env and re-answer every question
  -h, --help               Show this help

Examples:
  ./install.sh --domain jedu.ir --email ops@jedu.ir
  ./install.sh -y --skip-admin --shop-tag 1.2.0
  ./install.sh --no-tls --api-domain api.jedu.ir --shop-domain shop.jedu.ir \
               --admin-domain admin.jedu.ir
EOF
}

# ── Arguments ────────────────────────────────────────────────────────────────
NON_INTERACTIVE=false
RESET_ENV=false
SKIP_PULL=false
SKIP_ADMIN=false
REBUILD_SEARCH=false
TLS_ENABLED=true
DO_LOGIN=ask
REGISTRY_USER=""
REGISTRY_PASSWORD="${REGISTRY_PASSWORD:-}"
DOMAIN_SUFFIX=""
API_TAG=""
SHOP_TAG=""
ADMIN_TAG=""
ADMIN_IMAGE=""

# Values named here were given on the command line and outrank .env and the
# template; they are also never asked about.
declare -A FROM_FLAG=()
declare -A FROM_ENV=()

set_flag() { printf -v "$1" '%s' "$2"; FROM_FLAG["$1"]=1; }

# Both forms are accepted: `--opt value` and `--opt=value`.
while [[ "$#" -gt 0 ]]; do
    case "$1" in
        --domain) set_flag DOMAIN_SUFFIX "$2"; shift 2 ;;
        --domain=*) set_flag DOMAIN_SUFFIX "${1#*=}"; shift ;;
        --api-domain) set_flag API_DOMAIN "$2"; shift 2 ;;
        --api-domain=*) set_flag API_DOMAIN "${1#*=}"; shift ;;
        --shop-domain) set_flag SHOP_DOMAIN "$2"; shift 2 ;;
        --shop-domain=*) set_flag SHOP_DOMAIN "${1#*=}"; shift ;;
        --admin-domain) set_flag ADMIN_DOMAIN "$2"; shift 2 ;;
        --admin-domain=*) set_flag ADMIN_DOMAIN "${1#*=}"; shift ;;
        --email) set_flag ACME_EMAIL "$2"; shift 2 ;;
        --email=*) set_flag ACME_EMAIL "${1#*=}"; shift ;;
        --api-tag) set_flag API_TAG "$2"; shift 2 ;;
        --api-tag=*) set_flag API_TAG "${1#*=}"; shift ;;
        --shop-tag) set_flag SHOP_TAG "$2"; shift 2 ;;
        --shop-tag=*) set_flag SHOP_TAG "${1#*=}"; shift ;;
        --admin-tag) set_flag ADMIN_TAG "$2"; shift 2 ;;
        --admin-tag=*) set_flag ADMIN_TAG "${1#*=}"; shift ;;
        --admin-image) set_flag ADMIN_IMAGE "$2"; shift 2 ;;
        --admin-image=*) set_flag ADMIN_IMAGE "${1#*=}"; shift ;;
        --registry-user) set_flag REGISTRY_USER "$2"; shift 2 ;;
        --registry-user=*) set_flag REGISTRY_USER "${1#*=}"; shift ;;
        --registry-password) REGISTRY_PASSWORD="$2"; shift 2 ;;
        --registry-password=*) REGISTRY_PASSWORD="${1#*=}"; shift ;;
        -y|--yes) NON_INTERACTIVE=true; shift ;;
        --no-tls) TLS_ENABLED=false; shift ;;
        --no-login) DO_LOGIN=never; shift ;;
        --no-pull) SKIP_PULL=true; shift ;;
        --skip-admin) SKIP_ADMIN=true; shift ;;
        --rebuild-search) REBUILD_SEARCH=true; shift ;;
        --reset-env) RESET_ENV=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) log_error "Unknown parameter: $1"; usage >&2; exit 1 ;;
    esac
done

# A piped or redirected stdin has nobody to answer prompts.
INTERACTIVE=false
if [[ "$NON_INTERACTIVE" != true && -t 0 && -t 1 ]]; then
    INTERACTIVE=true
fi

# ── Docker Compose wrapper ───────────────────────────────────────────────────
compose() { docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"; }

# ── .env handling ────────────────────────────────────────────────────────────
# Template defaults are the single source of truth for values the operator does
# not override, so this file and .env.example cannot drift apart.
template_get() {
    [[ -f "$ENV_TEMPLATE" ]] || return 0
    local line
    line="$(grep -m1 -E "^$1=" "$ENV_TEMPLATE" || true)"
    printf '%s' "${line#*=}"
}

apply_template_defaults() {
    local key
    for key in "$@"; do
        if [[ -z "${!key:-}" ]]; then
            printf -v "$key" '%s' "$(template_get "$key")"
        fi
    done
}

load_existing_env() {
    [[ -f "$ENV_FILE" ]] || return 0
    local line key value loaded=0
    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ "$line" == *=* ]] || continue
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        key="${line%%=*}"
        value="${line#*=}"
        key="${key//[[:space:]]/}"
        [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
        # One layer of quotes, in case .env was hand-edited.
        if [[ "$value" == \"*\" || "$value" == \'*\' ]] && [[ "${#value}" -ge 2 ]]; then
            value="${value:1:${#value}-2}"
        fi
        [[ -n "${FROM_FLAG[$key]:-}" ]] && continue
        export "$key=$value"
        # An empty value counts as unanswered: it falls back to the template
        # default and is asked about again. Without this, a run with --no-tls
        # would leave ACME_EMAIL empty and the next TLS run would write a
        # Caddyfile with a bare `email` directive.
        if [[ -n "$value" ]]; then
            FROM_ENV["$key"]=1
            loaded=$((loaded + 1))
        fi
    done < "$ENV_FILE"
    log_info "Loaded ${loaded} non-empty values from the existing $(basename "$ENV_FILE")."
}

# Replace an existing key in place, or append it. awk keeps the file's comment
# structure and avoids sed's delimiter problems with base64 secrets. Edits go to
# $ENV_WORK, which is moved over .env once every key has been applied.
set_env() {
    local key="$1" value="$2" tmp
    value="${value//\\/\\\\}"
    tmp="$(mktemp "$SCRIPT_DIR/.env.XXXXXX")"
    awk -v k="$key" -v v="$value" '
        BEGIN { FS = "="; found = 0 }
        $1 == k { print k "=" v; found = 1; next }
        { print }
        END { if (!found) print k "=" v }
    ' "$ENV_WORK" > "$tmp"
    mv "$tmp" "$ENV_WORK"
}

# ── Prompts ──────────────────────────────────────────────────────────────────
ask() { # KEY "label" [required]
    local key="$1" label="$2" required="${3:-}" current answer
    [[ -n "${FROM_FLAG[$key]:-}" || -n "${FROM_ENV[$key]:-}" ]] && return 0
    current="${!key:-}"

    if [[ "$INTERACTIVE" == true ]]; then
        read -r -p "  ${label} [${current}]: " answer || true
        if [[ -n "$answer" ]]; then
            printf -v "$key" '%s' "$answer"
        fi
    fi

    if [[ -z "${!key:-}" && -n "$required" ]]; then
        log_error "${label} is required (${key} is empty)."
        exit 1
    fi
}

confirm() { # "question"
    [[ "$INTERACTIVE" == true ]] || return 0
    local answer
    read -r -p "  $1 [Y/n]: " answer || true
    case "${answer,,}" in
        n|no) return 1 ;;
        *) return 0 ;;
    esac
}

# ── Secrets ──────────────────────────────────────────────────────────────────
gen_secret() { openssl rand -hex "${1:-24}"; }
gen_app_key() { printf 'base64:%s' "$(openssl rand -base64 32)"; }

# ── Health ───────────────────────────────────────────────────────────────────
wait_for_health() { # container label timeout
    local container="$1" label="$2" timeout="${3:-$HEALTH_TIMEOUT}" waited=0 status
    printf '%b' "  Waiting for ${label} "
    while (( waited < timeout )); do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
            "$container" 2>/dev/null || echo missing)"
        case "$status" in
            healthy|running)
                printf '\n'
                log_success "${label} is up."
                return 0
                ;;
            unhealthy)
                printf '\n'
                log_warn "${label} reports unhealthy. Its logs explain why:"
                log_warn "  docker compose logs --tail=50 ${container}"
                return 0
                ;;
            exited|dead)
                printf '\n'
                log_error "${label} exited. Its logs explain why:"
                log_error "  docker compose logs --tail=50 ${container}"
                return 1
                ;;
        esac
        printf '.'
        sleep 5
        waited=$((waited + 5))
    done
    printf '\n'
    log_warn "${label} was not healthy after ${timeout}s."
    log_warn "  docker compose logs --tail=50 ${container}"
    return 1
}

# ── Preflight ────────────────────────────────────────────────────────────────
preflight() {
    log_step "Preflight"

    command -v docker >/dev/null 2>&1 || {
        log_error "docker is not installed."
        exit 1
    }
    docker compose version >/dev/null 2>&1 || {
        log_error "The docker compose v2 plugin is missing (the 'docker-compose' v1 binary will not work)."
        exit 1
    }
    docker info >/dev/null 2>&1 || {
        log_error "Cannot talk to the Docker daemon. Run this as root or as a member of the docker group."
        exit 1
    }
    command -v openssl >/dev/null 2>&1 || {
        log_error "openssl is required to generate the application key and passwords."
        exit 1
    }
    [[ -f "$ENV_TEMPLATE" ]] || {
        log_error "Missing ${ENV_TEMPLATE}."
        exit 1
    }
    log_success "docker $(docker version --format '{{.Server.Version}}') with compose v2."

    local port busy=false
    for port in 80 443; do
        if [[ "$TLS_ENABLED" == true || "$port" == 80 ]]; then
            if ss -ltnH "sport = :$port" 2>/dev/null | grep -q .; then
                log_warn "Port ${port} is already in use on this host."
                busy=true
            fi
        fi
    done
    if [[ "$busy" == true ]]; then
        log_warn "Stop whatever holds those ports (an existing nginx/Caddy/traefik), or use --no-tls behind it."
    fi
}

check_dns() {
    log_step "DNS"
    local host resolved ok=true
    for host in "$API_DOMAIN" "$SHOP_DOMAIN"; do
        resolved="$(getent ahostsv4 "$host" 2>/dev/null | awk 'NR==1 { print $1 }' || true)"
        if [[ -z "$resolved" ]]; then
            log_warn "${host} does not resolve yet. Point its A record at this server before publishing TLS."
            ok=false
        else
            log_info "${host} -> ${resolved}"
        fi
    done
    if [[ "$TLS_ENABLED" == true && "$ok" == false ]]; then
        log_warn "Caddy cannot obtain a certificate for a hostname that does not resolve; it retries in the background."
    fi
}

# ── Main ─────────────────────────────────────────────────────────────────────
# Every helper above is defined by now, so this is the earliest point the
# environment can be checked without relying on a not-yet-defined function.
preflight

if [[ "$RESET_ENV" == true && -f "$ENV_FILE" ]]; then
    backup="${ENV_FILE}.$(date +%Y%m%d%H%M%S).bak"
    cp "$ENV_FILE" "$backup"
    log_warn "Ignoring the existing .env (backed up to $(basename "$backup"))."
fi

if [[ "$RESET_ENV" != true ]]; then
    load_existing_env
fi

# Image coordinates and the keys whose template defaults are the real defaults.
apply_template_defaults JEDU_API_IMAGE JEDU_SHOP_IMAGE JEDU_ADMIN_IMAGE \
    CADDY_IMAGE REDIS_IMAGE POSTGRES_IMAGE TYPESENSE_IMAGE POSTGRES_MAX_LOCKS \
    API_DOMAIN SHOP_DOMAIN ADMIN_DOMAIN ACME_EMAIL HTTP_PORT HTTPS_PORT \
    POSTGRES_DB POSTGRES_USER APP_NAME LOG_CHANNEL LOG_LEVEL SESSION_DOMAIN \
    SESSION_SECURE_COOKIE MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME \
    MAIL_PASSWORD MAIL_SCHEME MAIL_FROM_ADDRESS MAIL_FROM_NAME HORIZON_USERNAME \
    SENTRY_LARAVEL_DSN SENTRY_ENVIRONMENT

# --domain derives the three hostnames, without overriding ones given directly.
if [[ -n "$DOMAIN_SUFFIX" ]]; then
    if [[ -z "${FROM_FLAG[API_DOMAIN]:-}${FROM_ENV[API_DOMAIN]:-}" ]]; then
        set_flag API_DOMAIN "api.${DOMAIN_SUFFIX}"
    fi
    if [[ -z "${FROM_FLAG[SHOP_DOMAIN]:-}${FROM_ENV[SHOP_DOMAIN]:-}" ]]; then
        set_flag SHOP_DOMAIN "shop.${DOMAIN_SUFFIX}"
    fi
    if [[ -z "${FROM_FLAG[ADMIN_DOMAIN]:-}${FROM_ENV[ADMIN_DOMAIN]:-}" ]]; then
        set_flag ADMIN_DOMAIN "admin.${DOMAIN_SUFFIX}"
    fi
fi

log_step "Configuration"
if [[ "$INTERACTIVE" == true ]]; then
    log_info "Press Enter to accept the value in brackets."
fi
ask API_DOMAIN "Public API hostname" required
ask SHOP_DOMAIN "Public storefront hostname" required
if [[ "$SKIP_ADMIN" != true ]]; then
    ask ADMIN_DOMAIN "Public admin hostname" required
fi
if [[ "$TLS_ENABLED" == true ]]; then
    ask ACME_EMAIL "Let's Encrypt account email" required
fi
ask POSTGRES_DB "PostgreSQL database name"
ask POSTGRES_USER "PostgreSQL user"
ask JEDU_API_IMAGE "API image"
ask JEDU_SHOP_IMAGE "Storefront image"
if [[ "$SKIP_ADMIN" != true ]]; then
    ask JEDU_ADMIN_IMAGE "Admin image"
fi

# Tags are applied to whatever the image reference ended up being.
with_tag() { printf '%s:%s' "${1%:*}" "$2"; }
if [[ -n "$API_TAG" ]]; then
    JEDU_API_IMAGE="$(with_tag "$JEDU_API_IMAGE" "$API_TAG")"
fi
if [[ -n "$SHOP_TAG" ]]; then
    JEDU_SHOP_IMAGE="$(with_tag "$JEDU_SHOP_IMAGE" "$SHOP_TAG")"
fi
if [[ -n "$ADMIN_TAG" ]]; then
    JEDU_ADMIN_IMAGE="$(with_tag "$JEDU_ADMIN_IMAGE" "$ADMIN_TAG")"
fi
if [[ -n "$ADMIN_IMAGE" ]]; then
    JEDU_ADMIN_IMAGE="$ADMIN_IMAGE"
fi

# Derived, never asked about.
if [[ "$TLS_ENABLED" == true ]]; then
    PUBLIC_SCHEME="https"
    CADDY_SITE_ADDRESS_PREFIX=""
    CADDY_GLOBAL_OPTIONS="email ${ACME_EMAIL}"
    SESSION_SECURE_COOKIE="true"
else
    PUBLIC_SCHEME="http"
    CADDY_SITE_ADDRESS_PREFIX="http://"
    CADDY_GLOBAL_OPTIONS=""
    SESSION_SECURE_COOKIE="false"
fi

if [[ "$SKIP_ADMIN" == true ]]; then
    # A plain-HTTP placeholder on a reserved TLD: no certificate is requested and
    # nothing is proxied, so the admin hostname simply has no site yet.
    CADDY_ADMIN_SITE="http://admin-disabled.invalid"
    CADDY_ADMIN_UPSTREAM="127.0.0.1:1"
else
    CADDY_ADMIN_SITE="${CADDY_SITE_ADDRESS_PREFIX}${ADMIN_DOMAIN}"
    CADDY_ADMIN_UPSTREAM="admin:80"
fi

POSTGRES_MAX_LOCKS="${POSTGRES_MAX_LOCKS:-256}"
HORIZON_USERNAME="${HORIZON_USERNAME:-ops}"
STOREFRONT_REVALIDATE_SECRET="${STOREFRONT_REVALIDATE_SECRET:-}"
TYPESENSE_API_KEY="${TYPESENSE_API_KEY:-}"
REDIS_PASSWORD="${REDIS_PASSWORD:-}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-}"
APP_KEY="${APP_KEY:-}"
HORIZON_PASSWORD="${HORIZON_PASSWORD:-}"

# Secrets are generated only when absent, which is what makes re-running safe.
gen_if_empty() { # KEY generator-args...
    local key="$1"
    shift
    [[ -n "${!key:-}" ]] && return 0
    printf -v "$key" '%s' "$("$@")"
    log_info "Generated ${key}."
}
gen_if_empty APP_KEY gen_app_key
gen_if_empty POSTGRES_PASSWORD gen_secret 24
gen_if_empty REDIS_PASSWORD gen_secret 24
gen_if_empty TYPESENSE_API_KEY gen_secret 24
gen_if_empty STOREFRONT_REVALIDATE_SECRET gen_secret 32
gen_if_empty HORIZON_PASSWORD gen_secret 16

# The domains are final now, so this is the point at which a missing A record is
# worth saying out loud (Caddy would otherwise fail to get a certificate later).
check_dns

log_step "Plan"
cat <<EOF
  Project            ${COMPOSE_PROJECT}  ($SCRIPT_DIR)
  API                ${PUBLIC_SCHEME}://${API_DOMAIN}       ${JEDU_API_IMAGE}
  Storefront         ${PUBLIC_SCHEME}://${SHOP_DOMAIN}       ${JEDU_SHOP_IMAGE}
  Admin              $(if [[ "$SKIP_ADMIN" == true ]]; then echo '(skipped)'; else echo "${PUBLIC_SCHEME}://${ADMIN_DOMAIN}       ${JEDU_ADMIN_IMAGE}"; fi)
  TLS                $(if [[ "$TLS_ENABLED" == true ]]; then echo "Let's Encrypt (ports ${HTTP_PORT}/${HTTPS_PORT})"; else echo "off, plain HTTP on ${HTTP_PORT}"; fi)
  Database           ${POSTGRES_DB} owned by ${POSTGRES_USER} on the stack's own PostgreSQL
  Search             Typesense (SCOUT_DRIVER=typesense)
  Secrets            $(if [[ -f "$ENV_FILE" && "$RESET_ENV" != true ]]; then echo 'kept from the existing .env'; else echo 'newly generated'; fi)
EOF

if [[ "$INTERACTIVE" == true ]] && ! confirm "Write .env and start the stack?"; then
    log_warn "Aborted; nothing was changed."
    exit 0
fi

log_step "Writing .env"
if [[ ! -f "$ENV_FILE" ]]; then
    cp "$ENV_TEMPLATE" "$ENV_FILE"
fi

# Edit a scratch copy and rename it over .env in one step: a `docker compose`
# running concurrently would otherwise be able to read a file whose secrets are
# still empty.
ENV_WORK="$(mktemp "$SCRIPT_DIR/.env.XXXXXX")"
trap 'rm -f "${ENV_WORK:-}"' EXIT
cp "$ENV_FILE" "$ENV_WORK"

set_env API_DOMAIN "$API_DOMAIN"
set_env SHOP_DOMAIN "$SHOP_DOMAIN"
set_env ADMIN_DOMAIN "$ADMIN_DOMAIN"
set_env ACME_EMAIL "${ACME_EMAIL:-}"
set_env PUBLIC_SCHEME "$PUBLIC_SCHEME"
set_env CADDY_GLOBAL_OPTIONS "$CADDY_GLOBAL_OPTIONS"
set_env CADDY_SITE_ADDRESS_PREFIX "$CADDY_SITE_ADDRESS_PREFIX"
set_env CADDY_ADMIN_SITE "$CADDY_ADMIN_SITE"
set_env CADDY_ADMIN_UPSTREAM "$CADDY_ADMIN_UPSTREAM"
set_env HTTP_PORT "$HTTP_PORT"
set_env HTTPS_PORT "$HTTPS_PORT"
set_env CADDY_IMAGE "$CADDY_IMAGE"
set_env JEDU_API_IMAGE "$JEDU_API_IMAGE"
set_env JEDU_SHOP_IMAGE "$JEDU_SHOP_IMAGE"
set_env JEDU_ADMIN_IMAGE "$JEDU_ADMIN_IMAGE"
set_env POSTGRES_MAX_LOCKS "$POSTGRES_MAX_LOCKS"
set_env POSTGRES_DB "$POSTGRES_DB"
set_env POSTGRES_USER "$POSTGRES_USER"
set_env POSTGRES_PASSWORD "$POSTGRES_PASSWORD"
set_env REDIS_PASSWORD "$REDIS_PASSWORD"
set_env TYPESENSE_API_KEY "$TYPESENSE_API_KEY"
set_env STOREFRONT_REVALIDATE_SECRET "$STOREFRONT_REVALIDATE_SECRET"
set_env APP_KEY "$APP_KEY"
set_env HORIZON_USERNAME "$HORIZON_USERNAME"
set_env HORIZON_PASSWORD "$HORIZON_PASSWORD"
set_env SESSION_SECURE_COOKIE "$SESSION_SECURE_COOKIE"
set_env APP_NAME "$APP_NAME"
set_env LOG_CHANNEL "$LOG_CHANNEL"
set_env LOG_LEVEL "$LOG_LEVEL"
set_env SESSION_DOMAIN "$SESSION_DOMAIN"
set_env MAIL_MAILER "$MAIL_MAILER"
set_env MAIL_FROM_ADDRESS "$MAIL_FROM_ADDRESS"
chmod 600 "$ENV_WORK"
mv "$ENV_WORK" "$ENV_FILE"
log_success "Wrote $(basename "$ENV_FILE") (mode 600)."

log_step "Validating the compose file"
compose config --quiet
log_success "docker-compose.yml resolves cleanly."

# ── Registry ─────────────────────────────────────────────────────────────────
if [[ "$SKIP_PULL" == true ]]; then
    log_warn "Skipping image pull (--no-pull)."
else
    log_step "Pulling images"
    if [[ "$DO_LOGIN" != never ]]; then
        if [[ -z "$REGISTRY_USER" && "$INTERACTIVE" == true ]]; then
            read -r -p "  Registry username for nexus.jedu.ir (empty to skip login): " REGISTRY_USER || true
        fi
        if [[ -n "$REGISTRY_USER" ]]; then
            if [[ -z "$REGISTRY_PASSWORD" && "$INTERACTIVE" == true ]]; then
                read -r -s -p "  Registry password: " REGISTRY_PASSWORD || true
                printf '\n'
            fi
            if [[ -n "$REGISTRY_PASSWORD" ]]; then
                printf '%s' "$REGISTRY_PASSWORD" \
                    | docker login nexus.jedu.ir -u "$REGISTRY_USER" --password-stdin
                log_success "Logged in to nexus.jedu.ir."
            else
                log_warn "No registry password given; pulling anonymously."
            fi
        fi
    fi

    pulled=("$JEDU_API_IMAGE" "$JEDU_SHOP_IMAGE")
    if [[ "$SKIP_ADMIN" != true ]]; then
        pulled+=("$JEDU_ADMIN_IMAGE")
    fi
    pulled+=("$CADDY_IMAGE" "$REDIS_IMAGE" "$POSTGRES_IMAGE" "$TYPESENSE_IMAGE")

    failed=()
    for image in "${pulled[@]}"; do
        if docker pull "$image" >/dev/null 2>&1; then
            log_success "pulled ${image}"
        else
            log_error "cannot pull ${image}"
            failed+=("$image")
        fi
    done

    if (( ${#failed[@]} > 0 )); then
        log_error "These images could not be pulled:"
        for image in "${failed[@]}"; do
            log_error "  ${image}"
        done
        if [[ "$SKIP_ADMIN" != true ]]; then
            log_error "If the admin image is not published yet, build it from JeduFront/admin-panel or rerun with --skip-admin."
        fi
        log_error "Retry the pull by hand to see the registry's error:  docker pull ${failed[0]}"
        exit 1
    fi
fi

# ── Up ───────────────────────────────────────────────────────────────────────
log_step "Starting the stack"
services=(postgres redis typesense)
if [[ "$SKIP_ADMIN" == true ]]; then
    services+=(api worker shop caddy)
else
    services+=(api worker shop admin caddy)
fi
compose up -d --remove-orphans "${services[@]}"

log_step "Waiting for the API"
if wait_for_health jedu-api "the API" "$HEALTH_TIMEOUT"; then
    # The engine setting is stored in the database, so it follows a fresh volume
    # rather than the image; deploy.php makes the same call on every deploy.
    if compose exec -T api php artisan pgroonga:engine typesense >/dev/null 2>&1; then
        log_success "Search engine set to Typesense."
    else
        log_warn "Could not set the search engine to Typesense. Run it by hand once the API is healthy:"
        log_warn "  docker compose exec api php artisan pgroonga:engine typesense"
    fi
fi
wait_for_health jedu-worker "the queue worker" 120 || true
if [[ "$SKIP_ADMIN" != true ]]; then
    wait_for_health jedu-admin "the admin panel" 60 || true
fi
wait_for_health jedu-shop "the storefront" 120 || true

if [[ "$REBUILD_SEARCH" == true ]]; then
    log_step "Indexing products into Typesense"
    compose exec -T api php artisan products:index-availability --sync || log_warn "products:index-availability failed."
    compose exec -T api php artisan scout:import "App\\Models\\Product" || log_warn "scout:import failed."
fi

# ── Summary ──────────────────────────────────────────────────────────────────
log_step "Done"
cat <<EOF
  API            ${PUBLIC_SCHEME}://${API_DOMAIN}
  API docs       ${PUBLIC_SCHEME}://${API_DOMAIN}/docs
  Horizon        ${PUBLIC_SCHEME}://${API_DOMAIN}/horizon   (basic auth: ${HORIZON_USERNAME} / the HORIZON_PASSWORD in .env)
  Storefront     ${PUBLIC_SCHEME}://${SHOP_DOMAIN}
  Admin          $(if [[ "$SKIP_ADMIN" == true ]]; then echo '(not installed; rerun without --skip-admin)'; else echo "${PUBLIC_SCHEME}://${ADMIN_DOMAIN}"; fi)

  Status         docker compose ps
  Logs           docker compose logs -f api
  Shell          docker compose exec api bash
  Stop           docker compose down          (add -v to also delete every volume)

  Before announcing this deployment, work through the go-live checklist in
  README.md: mail delivery, the trusted-proxy config, DNS/TLS verification and a
  database backup.
EOF
