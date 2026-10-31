#!/usr/bin/env bash
#
# Jedu production installer.
#
# Brings up the stack from images published to Nexus and writes ./.env, which
# afterwards is the single source of truth for every plain `docker compose`
# command in this directory.
#
# Three questions decide the shape, and each is answered per deployment:
#
#   1. Where does TLS terminate?
#        caddy     Caddy runs here and obtains certificates (self-contained).
#        nginx     nginx already runs here and terminates TLS; no Caddy.
#        external  another edge (NPMplus, a load balancer, another server)
#                  terminates TLS and forwards plain HTTP here.
#   2. Who routes hostnames to the containers?
#        caddy     the bundled Caddy container.
#        nginx     this host's nginx; the containers are published on
#                  127.0.0.1 and nginx proxies to them.
#      The router follows the edge, except for edge=external where it is a
#      choice (default: nginx when nginx is installed, otherwise caddy).
#   3. Where do PostgreSQL, Redis and Typesense live? Independently per service:
#        bundled    inside this compose project.
#        external   another server or a managed service, reached by host:port.
#        container  a container of another compose project, reached by name on
#                   an existing Docker network.
#
# install.sh selects the compose files and profiles by writing COMPOSE_FILE and
# COMPOSE_PROFILES into .env, so nothing else needs special flags:
#
#   docker compose ps
#   docker compose logs -f api
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
readonly COMPOSE_PROJECT="jedu-production"
readonly HEALTH_TIMEOUT=600
NGINX_RENDERED=""

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

Topology:
  --edge <mode>            caddy    Caddy on this host terminates TLS (default)
                           nginx    nginx on this host terminates TLS
                           external an upstream edge (NPMplus/LB) terminates TLS
  --router <mode>          caddy|nginx. Only meaningful with --edge external;
                           otherwise it follows the edge. Default externally:
                           nginx when nginx is installed, else caddy.
  --edge-proxies <cidrs>   Addresses/CIDRs of the upstream edge. Required with
                           --edge external. Space- or comma-separated, e.g.
                           "10.0.0.5/32" or "10.0.0.0/24,10.0.1.0/24".
  --no-tls                 The public URLs are plain http (no TLS anywhere).
                           Only valid with --edge external.

Datastores (each independent):
  --db <mode>              bundled|external|container  (default: bundled)
  --redis <mode>           bundled|external|container  (default: bundled)
  --typesense <mode>       bundled|external|container  (default: bundled)
  --db-host <host>         PostgreSQL host or container name
  --db-port <port>         PostgreSQL port
  --redis-host <host>      Redis host or container name
  --redis-port <port>      Redis port
  --redis-username <user>  Redis ACL user (optional)
  --typesense-host <host>  Typesense host or container name
  --typesense-port <port>  Typesense port
  --typesense-protocol <p> http|https (default: http)
  --datastore-network <n>  Existing Docker network the containerised datastores
                           are on. Required with any --*-mode container.

Domains:
  --domain <suffix>        Derive all three hostnames from one suffix, e.g.
                           `--domain jedu.ir` gives api/shop/admin.jedu.ir
  --api-domain <host>      Public API hostname
  --shop-domain <host>     Public storefront hostname
  --admin-domain <host>    Public admin hostname
  --email <address>        Let's Encrypt account email (required with --edge caddy)

Published ports and binds:
  --http-port <port>       Plain HTTP port (default: 80)
  --https-port <port>      TLS port (default: 443)
  --http-bind <address>    Address nginx or Caddy binds for HTTP; for nginx,
                           use this server's private IPv4, not the edge address
                           (default: 0.0.0.0; use the private IP behind an edge)
  --caddy-bind <address>   Address Caddy publishes (default: 0.0.0.0)
  --public-port <port>     TLS port the browser uses, forwarded as
                           X-Forwarded-Port (default: 443)

Host nginx (router=nginx):
  --tls-cert <path>        Certificate chain for --edge nginx
                           (default: /etc/letsencrypt/live/<api-domain>/fullchain.pem)
  --tls-key <path>         Private key for --edge nginx
  --nginx-config-name <name> Name for the generated nginx .conf file
  --emit-nginx-conf        Write the generated nginx config and print install
                           steps instead of touching the running nginx
  --replace-nginx-site     If another server block already names one of these
                           hostnames, back it up and take the hostname over

Images:
  --api-tag <tag>          Tag for the API image (default: latest)
  --shop-tag <tag>         Tag for the storefront image (default: 1.0.0)
  --admin-tag <tag>        Tag for the admin image (default: 1.0.0)
  --admin-image <ref>      Full admin image reference, overrides --admin-tag
  --skip-admin             Do not pull or start the admin panel; the admin
                           hostname answers 502 until you install it

Behaviour:
  -y, --yes                Never prompt; fail if a required value is missing
  --registry-user <user>   Log in to the registry before pulling
  --registry-password <pw> Password for --registry-user (or set REGISTRY_PASSWORD)
  --no-login               Never attempt `docker login`
  --skip-pull, --no-pull   Use the images already on this host
  --rebuild-search         Index products into Typesense after the stack is up
  --reset-env              Ignore the existing .env and re-answer every question
  -h, --help               Show this help

Examples:
  # Self-contained: Caddy obtains certificates, all datastores bundled.
  ./install.sh --edge caddy --domain jedu.ir --email ops@jedu.ir

  # Behind NPMplus on a host that already runs nginx, datastores elsewhere.
  ./install.sh --edge external --router nginx --edge-proxies 10.0.0.5/32 \
      --db external --db-host 10.0.0.10 --db-port 5433 \
      --redis external --redis-host 10.0.0.10 \
      --typesense container --typesense-host jedu-typesense \
      --datastore-network shared-data

  # Behind NPMplus with no nginx on the host: bundled Caddy as plain HTTP router.
  ./install.sh --edge external --router caddy --edge-proxies 10.0.0.0/24
EOF
}

# ── Arguments ────────────────────────────────────────────────────────────────
NON_INTERACTIVE=false
RESET_ENV=false
SKIP_PULL=false
SKIP_ADMIN=false
REBUILD_SEARCH=false
EMIT_NGINX_CONF=false
REPLACE_NGINX_SITE=false
NO_TLS=false
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
        --edge) set_flag EDGE_MODE "$2"; shift 2 ;;
        --edge=*) set_flag EDGE_MODE "${1#*=}"; shift ;;
        --router) set_flag ROUTER "$2"; shift 2 ;;
        --router=*) set_flag ROUTER "${1#*=}"; shift ;;
        --edge-proxies) set_flag EDGE_PROXIES "$2"; shift 2 ;;
        --edge-proxies=*) set_flag EDGE_PROXIES "${1#*=}"; shift ;;
        --db) set_flag DB_MODE "$2"; shift 2 ;;
        --db=*) set_flag DB_MODE "${1#*=}"; shift ;;
        --redis) set_flag REDIS_MODE "$2"; shift 2 ;;
        --redis=*) set_flag REDIS_MODE "${1#*=}"; shift ;;
        --typesense) set_flag TYPESENSE_MODE "$2"; shift 2 ;;
        --typesense=*) set_flag TYPESENSE_MODE "${1#*=}"; shift ;;
        --db-host) set_flag DB_HOST "$2"; shift 2 ;;
        --db-host=*) set_flag DB_HOST "${1#*=}"; shift ;;
        --db-port) set_flag DB_PORT "$2"; shift 2 ;;
        --db-port=*) set_flag DB_PORT "${1#*=}"; shift ;;
        --redis-host) set_flag REDIS_HOST "$2"; shift 2 ;;
        --redis-host=*) set_flag REDIS_HOST "${1#*=}"; shift ;;
        --redis-port) set_flag REDIS_PORT "$2"; shift 2 ;;
        --redis-port=*) set_flag REDIS_PORT "${1#*=}"; shift ;;
        --redis-username) set_flag REDIS_USERNAME "$2"; shift 2 ;;
        --redis-username=*) set_flag REDIS_USERNAME "${1#*=}"; shift ;;
        --typesense-host) set_flag TYPESENSE_HOST "$2"; shift 2 ;;
        --typesense-host=*) set_flag TYPESENSE_HOST "${1#*=}"; shift ;;
        --typesense-port) set_flag TYPESENSE_PORT "$2"; shift 2 ;;
        --typesense-port=*) set_flag TYPESENSE_PORT "${1#*=}"; shift ;;
        --typesense-protocol) set_flag TYPESENSE_PROTOCOL "$2"; shift 2 ;;
        --typesense-protocol=*) set_flag TYPESENSE_PROTOCOL "${1#*=}"; shift ;;
        --datastore-network) set_flag DATASTORE_NETWORK "$2"; shift 2 ;;
        --datastore-network=*) set_flag DATASTORE_NETWORK "${1#*=}"; shift ;;
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
        --http-port) set_flag HTTP_PORT "$2"; shift 2 ;;
        --http-port=*) set_flag HTTP_PORT "${1#*=}"; shift ;;
        --https-port) set_flag HTTPS_PORT "$2"; shift 2 ;;
        --https-port=*) set_flag HTTPS_PORT "${1#*=}"; shift ;;
        --public-port) set_flag PUBLIC_PORT "$2"; shift 2 ;;
        --public-port=*) set_flag PUBLIC_PORT "${1#*=}"; shift ;;
        --http-bind) set_flag HTTP_BIND "$2"; shift 2 ;;
        --http-bind=*) set_flag HTTP_BIND "${1#*=}"; shift ;;
        --caddy-bind) set_flag CADDY_BIND "$2"; shift 2 ;;
        --caddy-bind=*) set_flag CADDY_BIND "${1#*=}"; shift ;;
        --tls-cert) set_flag NGINX_TLS_CERT "$2"; shift 2 ;;
        --tls-cert=*) set_flag NGINX_TLS_CERT "${1#*=}"; shift ;;
        --tls-key) set_flag NGINX_TLS_KEY "$2"; shift 2 ;;
        --tls-key=*) set_flag NGINX_TLS_KEY "${1#*=}"; shift ;;
        --nginx-config-name) set_flag NGINX_CONFIG_NAME "$2"; shift 2 ;;
        --nginx-config-name=*) set_flag NGINX_CONFIG_NAME "${1#*=}"; shift ;;
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
        --no-tls) NO_TLS=true; shift ;;
        --no-login) DO_LOGIN=never; shift ;;
        --skip-pull|--no-pull) SKIP_PULL=true; shift ;;
        --skip-admin) SKIP_ADMIN=true; shift ;;
        --rebuild-search) REBUILD_SEARCH=true; shift ;;
        --reset-env) RESET_ENV=true; shift ;;
        --emit-nginx-conf) EMIT_NGINX_CONF=true; shift ;;
        --replace-nginx-site) REPLACE_NGINX_SITE=true; shift ;;
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
# No -f here: COMPOSE_FILE in .env selects the mode files, and repeating it on
# the command line would override the selection.
compose() { docker compose --env-file "$ENV_FILE" "$@"; }

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
        # default and is asked about again.
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

ask_secret() { # KEY "label" [required]
    local key="$1" label="$2" required="${3:-}" answer
    [[ -n "${FROM_FLAG[$key]:-}" || -n "${FROM_ENV[$key]:-}" ]] && return 0
    if [[ "$INTERACTIVE" == true ]]; then
        read -r -s -p "  ${label} (hidden; Enter keeps the current value): " answer || true
        printf '\n'
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

ask_choice() { # KEY "label" "opt1 opt2 ..."
    local key="$1" label="$2" options="$3" answer index=1 option choices
    if [[ -z "${FROM_FLAG[$key]:-}${FROM_ENV[$key]:-}" && "$INTERACTIVE" == true ]]; then
        read -r -a choices <<< "$options"
        log_info "$label"
        for option in "${choices[@]}"; do
            printf '   %d) %s\n' "$index" "$option"
            index=$((index + 1))
        done
        read -r -p "  Choice [${!key:-}]: " answer || true
        if [[ -n "$answer" ]]; then
            if [[ "$answer" =~ ^[0-9]+$ ]] && (( answer >= 1 && answer <= ${#choices[@]} )); then
                printf -v "$key" '%s' "${choices[$((answer - 1))]}"
            else
                printf -v "$key" '%s' "$answer"
            fi
        fi
    fi
    case " $options " in
        *" ${!key:-} "*) ;;
        *) log_error "${label}: '${!key:-}' is not one of: $options"; exit 1 ;;
    esac
}

# ── Secrets ──────────────────────────────────────────────────────────────────
gen_secret() { openssl rand -hex "${1:-24}"; }
gen_app_key() { printf 'base64:%s' "$(openssl rand -base64 32)"; }

gen_if_empty() { # KEY generator-args...
    local key="$1"
    shift
    [[ -n "${!key:-}" ]] && return 0
    printf -v "$key" '%s' "$("$@")"
    log_info "Generated ${key}."
}

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

# ── Host detection ───────────────────────────────────────────────────────────
NGINX_PRESENT=false
NGINX_RUNNING=false
detect_nginx() {
    if command -v nginx >/dev/null 2>&1; then
        NGINX_PRESENT=true
        if systemctl is-active --quiet nginx 2>/dev/null; then
            NGINX_RUNNING=true
        fi
    fi
}

have_root() { [[ "${EUID}" -eq 0 ]] || sudo -n true 2>/dev/null; }
as_root() { if [[ "${EUID}" -eq 0 ]]; then "$@"; else sudo -- "$@"; fi; }

prepare_data_directories() {
    local data_root="$JEDU_DATA_DIR"
    if [[ "$data_root" != /* ]]; then
        data_root="$SCRIPT_DIR/$data_root"
    fi

    mkdir -p \
        "$data_root/postgres" \
        "$data_root/redis" \
        "$data_root/typesense" \
        "$data_root/storage/app" \
        "$data_root/storage/logs" \
        "$data_root/caddy/data" \
        "$data_root/caddy/config"

    if ! as_root chown 1000:1000 "$data_root/storage/app" "$data_root/storage/logs"; then
        log_error "Could not set Laravel storage ownership to uid/gid 1000."
        log_error "Grant sudo access or set ownership on ${data_root}/storage/app and storage/logs manually."
        exit 1
    fi
}

port_busy() { # port
    command -v ss >/dev/null 2>&1 || return 1
    [[ -n "$(ss -ltnH "sport = :$1" 2>/dev/null || true)" ]]
}

# True when one of our own containers already publishes the port: a re-run is
# allowed to keep its ports, a foreign listener is not.
port_owned_by_project() { # port
    command -v docker >/dev/null 2>&1 || return 1
    docker ps --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" --format '{{.Ports}}' 2>/dev/null \
        | grep -q ":$1->"
}

require_port() { # port "what"
    local port="$1" what="$2"
    if port_busy "$port" && ! port_owned_by_project "$port"; then
        log_error "Port ${port} is already in use (${what})."
        log_error "Stop whatever holds it, or choose another port."
        exit 1
    fi
}

validate_nginx_bind() {
    local address="$HTTP_BIND"

    if [[ "$address" == 0.0.0.0 || "$address" == 127.* ]]; then
        log_error "HTTP_BIND must be this server's private IPv4 address, not ${address}."
        log_error "Use the address the upstream edge connects to on this server (for example, 10.0.0.15)."
        exit 1
    fi

    if ! command -v ip >/dev/null 2>&1 || ! ip -o -4 address show | awk -v address="$address" '
        { split($4, cidr, "/"); if (cidr[1] == address) found = 1 }
        END { exit !found }
    '; then
        log_error "HTTP_BIND (${address}) is not assigned to this server."
        log_error "Set it to this server's private IPv4 address, not the upstream edge's address."
        exit 1
    fi
}

validate_nginx_config_name() {
    if [[ ! "$NGINX_CONFIG_NAME" =~ ^[[:alnum:]][[:alnum:]_.-]*\.conf$ ]]; then
        log_error "NGINX_CONFIG_NAME must be a plain filename ending in .conf (got '${NGINX_CONFIG_NAME}')."
        exit 1
    fi
}

# ── Preflight ────────────────────────────────────────────────────────────────
preflight_tools() {
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
    detect_nginx
    log_success "docker $(docker version --format '{{.Server.Version}}') with compose v2."
    if [[ "$NGINX_PRESENT" == true ]]; then
        log_info "nginx detected on this host ($([[ "$NGINX_RUNNING" == true ]] && echo running || echo "not running"))."
    fi
}

check_dns() {
    [[ "$EDGE_MODE" == external ]] && return 0
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
    if [[ "$EDGE_MODE" == caddy && "$ok" == false ]]; then
        log_warn "Caddy cannot obtain a certificate for a hostname that does not resolve; it retries in the background."
    fi
}

# ── Nginx rendering ──────────────────────────────────────────────────────────
render_template() { # src dst KEY=VALUE...
    local src="$1" dst="$2" pair key value tmp
    shift 2
    cp "$src" "$dst"
    for pair in "$@"; do
        key="${pair%%=*}"
        value="${pair#*=}"
        value="${value//\\/\\\\}"
        value="${value//&/\\&}"
        tmp="$(mktemp)"
        awk -v pat="__${key}__" -v rep="$value" '{ gsub(pat, rep); print }' "$dst" > "$tmp"
        mv "$tmp" "$dst"
    done
}

nginx_version() { nginx -v 2>&1 | sed -n 's#.*nginx/\([0-9][0-9.]*\).*#\1#p'; }

nginx_supports_http2_directive() {
    local version
    version="$(nginx_version)"
    [[ -n "$version" ]] || return 1
    awk -v v="$version" 'BEGIN {
        n = split(v, a, ".")
        if (a[1] > 1) exit 0
        if (a[1] == 1 && a[2] > 25) exit 0
        if (a[1] == 1 && a[2] == 25 && a[3] >= 1) exit 0
        exit 1
    }'
}

render_nginx() {
    local template="$1" real_ip_lines="" cidr
    template="$SCRIPT_DIR/nginx/${template}"

    if [[ "$ROUTER" == nginx && "$EDGE_MODE" == external ]]; then
        for cidr in $EDGE_PROXIES; do
            real_ip_lines+="set_real_ip_from ${cidr};"$'\n'
        done
        real_ip_lines+="real_ip_header X-Forwarded-For;"$'\n'
        real_ip_lines+="real_ip_recursive on;"
    fi

    render_template "$template" "$NGINX_RENDERED" \
        "API_DOMAIN=$API_DOMAIN" \
        "SHOP_DOMAIN=$SHOP_DOMAIN" \
        "ADMIN_DOMAIN=$ADMIN_DOMAIN" \
        "API_UPSTREAM=127.0.0.1:${API_LOOPBACK_PORT}" \
        "SHOP_UPSTREAM=127.0.0.1:${SHOP_LOOPBACK_PORT}" \
        "ADMIN_UPSTREAM=127.0.0.1:${ADMIN_LOOPBACK_PORT}" \
        "HTTP_LISTEN=${HTTP_BIND}:${HTTP_PORT}" \
        "LISTEN_TLS=${NGINX_LISTEN_TLS}" \
        "HTTP2_LINE=${NGINX_HTTP2_LINE}" \
        "TLS_CERT=${NGINX_TLS_CERT}" \
        "TLS_KEY=${NGINX_TLS_KEY}" \
        "XFP_PORT=${NGINX_XFP_PORT}" \
        "XFP_PROTO=${NGINX_XFP_PROTO}" \
        "REAL_IP_LINES=${real_ip_lines}"

    if [[ "$SKIP_ADMIN" == true ]]; then
        sed -i '/^# __ADMIN_BEGIN__$/,/^# __ADMIN_END__$/d' "$NGINX_RENDERED"
    else
        sed -i '/^# __ADMIN_BEGIN__$/d; /^# __ADMIN_END__$/d' "$NGINX_RENDERED"
    fi
}

# Finds foreign server blocks that already claim one of our hostnames. Parses
# `nginx -T`, which prefixes every file with "# configuration file <path>:". The
# file this installer writes is excluded: it is ours and replaced on purpose.
nginx_conflicts() {
    local domains="$API_DOMAIN $SHOP_DOMAIN $ADMIN_DOMAIN"
    [[ "$SKIP_ADMIN" == true ]] && domains="$API_DOMAIN $SHOP_DOMAIN"
    as_root nginx -T 2>/dev/null | awk -v domains=" $domains " -v ours="$NGINX_DEST $NGINX_LINK" '
        /^# configuration file / {
            file = $4
            sub(/:$/, "", file)
            next
        }
        /^[[:space:]]*server_name[[:space:]]/ {
            for (i = 2; i <= NF; i++) {
                name = $i
                sub(/;$/, "", name)
                if (index(domains, " " name " ") > 0 && index(ours, file) == 0) {
                    print file
                    break
                }
            }
        }' | sort -u
}

apply_nginx() {
    local conflicts file backup target restored=0
    local -a backup_list=()
    NGINX_APPLIED=false

    if [[ "$EMIT_NGINX_CONF" == true ]]; then
        log_success "Wrote ${NGINX_RENDERED#"$SCRIPT_DIR"/}. Install it with:"
        log_info "  sudo cp ${NGINX_RENDERED#"$SCRIPT_DIR"/} ${NGINX_DEST} && sudo nginx -t && sudo systemctl reload nginx"
        return 0
    fi

    if ! have_root; then
        log_warn "No passwordless sudo: cannot install the nginx site automatically."
        log_warn "The rendered file is at ${NGINX_RENDERED#"$SCRIPT_DIR"/}. Install it with:"
        log_warn "  sudo cp ${NGINX_RENDERED#"$SCRIPT_DIR"/} ${NGINX_DEST}"
        log_warn "  sudo nginx -t && sudo systemctl reload nginx"
        return 0
    fi

    if [[ -L /etc/nginx/sites-enabled/jedu-docker.conf && ! -e /etc/nginx/sites-enabled/jedu-docker.conf ]]; then
        local legacy_link=/etc/nginx/sites-enabled/jedu-docker.conf
        local legacy_target legacy_backup
        legacy_target="$(as_root readlink "$legacy_link")"
        if [[ -d /etc/nginx/sites-available ]]; then
            legacy_backup="/etc/nginx/sites-available/jedu-docker.conf.jedu-backup-$(date +%Y%m%d%H%M%S)"
            as_root mv "$legacy_link" "$legacy_backup"
            log_warn "Archived broken legacy nginx symlink ${legacy_link} -> ${legacy_target} as ${legacy_backup}."
        else
            as_root rm "$legacy_link"
            log_warn "Removed broken legacy nginx symlink ${legacy_link} -> ${legacy_target}."
        fi
    fi

    if ! as_root nginx -t; then
        log_error "The existing nginx configuration is invalid. Fix it before installing the Jedu site."
        return 1
    fi

    conflicts="$(nginx_conflicts || true)"
    for file in $conflicts; do
        [[ -f "$file" ]] || continue
        if [[ "$REPLACE_NGINX_SITE" != true ]]; then
            log_error "Another nginx server block already answers one of these hostnames:"
            log_error "  ${file}"
            log_error "That is usually a live deployment (a Deployer release, an"
            log_error "older install). Nothing was changed."
            log_error "Move it out of the include directory, choose different hostnames,"
            log_error "or rerun with --replace-nginx-site to back it up and take over."
            exit 1
        fi
        if [[ -L "$file" ]]; then
            target="$(as_root readlink -f "$file")"
            backup="${target}.jedu-backup-$(date +%Y%m%d%H%M%S)"
            as_root cp -p "$target" "$backup"
            as_root rm "$file"
        else
            backup="${file}.jedu-backup-$(date +%Y%m%d%H%M%S)"
            as_root mv "$file" "$backup"
        fi
        log_warn "Backed up ${file} as ${backup}."
        backup_list+=("${file}|${backup}")
    done

    as_root install -m 0644 "$NGINX_RENDERED" "$NGINX_DEST"
    if [[ -n "$NGINX_LINK" ]]; then
        as_root ln -sfn "$NGINX_DEST" "$NGINX_LINK"
    fi

    if ! as_root nginx -t; then
        log_error "nginx rejected the generated configuration; rolling back."
        as_root rm -f "$NGINX_DEST"
        [[ -n "${NGINX_LINK:-}" ]] && as_root rm -f "$NGINX_LINK"
        for pair in "${backup_list[@]:-}"; do
            [[ -n "$pair" ]] || continue
            as_root mv "${pair#*|}" "${pair%%|*}"
            restored=$((restored + 1))
        done
        log_error "Restored ${restored} backup(s). Nothing was started."
        exit 1
    fi

    if as_root systemctl reload nginx 2>/dev/null; then
        log_success "nginx reloaded."
    elif as_root nginx -s reload 2>/dev/null; then
        log_success "nginx reloaded."
    else
        log_warn "Could not reload nginx automatically; run: sudo systemctl reload nginx"
    fi
    NGINX_APPLIED=true
}

# ── Main ─────────────────────────────────────────────────────────────────────
preflight_tools

if [[ "$RESET_ENV" == true && -f "$ENV_FILE" ]]; then
    backup="${ENV_FILE}.$(date +%Y%m%d%H%M%S).bak"
    cp "$ENV_FILE" "$backup"
    log_warn "Ignoring the existing .env (backed up to $(basename "$backup"))."
fi

if [[ "$RESET_ENV" != true ]]; then
    load_existing_env
fi

# Template defaults for everything the operator may leave out. Derived values
# (COMPOSE_FILE, COMPOSE_PROFILES, URLs, Caddy settings) are not here: they are
# recomputed from the choices below on every run.
apply_template_defaults JEDU_API_IMAGE JEDU_SHOP_IMAGE JEDU_ADMIN_IMAGE \
    CADDY_IMAGE POSTGRES_IMAGE REDIS_IMAGE TYPESENSE_IMAGE POSTGRES_MAX_LOCKS \
    EDGE_MODE DB_MODE REDIS_MODE TYPESENSE_MODE DB_PORT REDIS_PORT \
    TYPESENSE_PORT TYPESENSE_PROTOCOL DATASTORE_NETWORK \
    API_DOMAIN SHOP_DOMAIN ADMIN_DOMAIN ACME_EMAIL JEDU_DATA_DIR \
    HTTP_PORT HTTPS_PORT PUBLIC_PORT HTTP_BIND CADDY_BIND NGINX_CONFIG_NAME \
    API_LOOPBACK_PORT SHOP_LOOPBACK_PORT ADMIN_LOOPBACK_PORT \
    POSTGRES_DB POSTGRES_USER APP_NAME LOG_CHANNEL LOG_LEVEL SESSION_DOMAIN \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_SCHEME \
    MAIL_FROM_ADDRESS MAIL_FROM_NAME HORIZON_USERNAME SENTRY_LARAVEL_DSN \
    SENTRY_ENVIRONMENT CORS_MAX_AGE

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

# ── Edge and router ──────────────────────────────────────────────────────────
if [[ -z "${FROM_FLAG[EDGE_MODE]:-}${FROM_ENV[EDGE_MODE]:-}" && "$INTERACTIVE" == true ]]; then
    answer=""
    log_info "Where does TLS terminate?"
    printf '   1) caddy     this server (Caddy obtains and renews certificates)\n'
    printf '   2) nginx     this server (nginx already terminates TLS)\n'
    printf '   3) external  another edge (NPMplus/LB); this server may have no public IP\n'
    read -r -p "  Choice [${EDGE_MODE:-caddy}]: " answer || true
    case "${answer,,}" in
        ''|1|caddy) EDGE_MODE=caddy ;;
        2|nginx)    EDGE_MODE=nginx ;;
        3|external) EDGE_MODE=external ;;
        *) log_error "Unknown edge: $answer"; exit 1 ;;
    esac
fi
case "${EDGE_MODE:-}" in
    caddy|nginx|external) ;;
    *) log_error "EDGE_MODE must be caddy, nginx or external (got '${EDGE_MODE:-}')."; exit 1 ;;
esac

if [[ "$EDGE_MODE" == nginx ]]; then
    ROUTER=nginx
elif [[ "$EDGE_MODE" == caddy ]]; then
    ROUTER=caddy
else
    if [[ -z "${ROUTER:-}" ]]; then
        if [[ "$NGINX_PRESENT" == true ]]; then ROUTER=nginx; else ROUTER=caddy; fi
    fi
    ask_choice ROUTER "Which router forwards hostnames to the containers?" "caddy nginx"
fi
case "$ROUTER" in
    caddy|nginx) ;;
    *) log_error "ROUTER must be caddy or nginx (got '${ROUTER}')."; exit 1 ;;
esac

if [[ "$NO_TLS" == true && "$EDGE_MODE" != external ]]; then
    log_error "--no-tls means 'the edge is plain http too' and only makes sense with --edge external."
    log_error "For a host-level proxy that terminates TLS, use --edge external --router caddy|nginx."
    exit 1
fi

if [[ "$ROUTER" == nginx && "$NGINX_PRESENT" != true ]]; then
    log_error "router=nginx was chosen but nginx is not installed on this host."
    log_error "Install nginx, or use --edge external --router caddy / --edge caddy."
    exit 1
fi

if [[ "$EDGE_MODE" == external ]]; then
    EDGE_PROXIES="$(printf '%s' "${EDGE_PROXIES:-}" | tr ',' ' ' | xargs)"
    if [[ "$INTERACTIVE" == true && -z "$EDGE_PROXIES" ]]; then
        log_info "Addresses/CIDRs of the upstream edge (spoofing protection against X-Forwarded-For)."
        read -r -p "  Edge proxies [e.g. 10.0.0.5/32]: " EDGE_PROXIES || true
        EDGE_PROXIES="$(printf '%s' "$EDGE_PROXIES" | tr ',' ' ' | xargs)"
    fi
    if [[ -z "$EDGE_PROXIES" ]]; then
        log_error "edge=external requires --edge-proxies (the edge's addresses/CIDRs)."
        exit 1
    fi
    log_info "Trusted edge address(es): ${EDGE_PROXIES}"
fi

if [[ "$EDGE_MODE" == caddy ]]; then
    ask ACME_EMAIL "Let's Encrypt account email" required
fi

if [[ "$EDGE_MODE" == external ]]; then
    if [[ "$ROUTER" == caddy ]]; then
        ask CADDY_BIND "Address Caddy publishes on (private IP or 0.0.0.0)"
    else
        ask HTTP_BIND "This server's private IPv4 address nginx listens on (not the edge address)"
        validate_nginx_bind
    fi
fi

if [[ "$ROUTER" == nginx ]]; then
    ask NGINX_CONFIG_NAME "Filename for the generated nginx config (include .conf)"
    validate_nginx_config_name
    NGINX_RENDERED="$SCRIPT_DIR/nginx/$NGINX_CONFIG_NAME"
fi

# ── Datastores ───────────────────────────────────────────────────────────────
ask_datastore() { # KEY "label"
    local key="$1" label="$2" answer
    if [[ -z "${FROM_FLAG[$key]:-}${FROM_ENV[$key]:-}" && "$INTERACTIVE" == true ]]; then
        log_info "${label}"
        printf '   1) bundled    run it inside this compose project\n'
        printf '   2) external   another server or a managed service (host:port)\n'
        printf '   3) container  a container of another compose project\n'
        read -r -p "  Choice [${!key:-bundled}]: " answer || true
        case "${answer,,}" in
            ''|1|bundled) printf -v "$key" '%s' bundled ;;
            2|external)   printf -v "$key" '%s' external ;;
            3|container)  printf -v "$key" '%s' container ;;
            *) log_error "Unknown choice for ${label}: $answer"; exit 1 ;;
        esac
    fi
    case "${!key}" in
        bundled|external|container) ;;
        *) log_error "${label}: '${!key}' must be bundled, external or container."; exit 1 ;;
    esac
}

ask_datastore DB_MODE "PostgreSQL"
ask_datastore REDIS_MODE "Redis"
ask_datastore TYPESENSE_MODE "Typesense"

# Force the built-in coordinates for bundled services and clear a stale bundled
# hostname when a service becomes external (so the prompt is asked, not skipped).
if [[ "$DB_MODE" == bundled ]]; then
    DB_HOST=postgres
    DB_PORT=5432
elif [[ -z "${FROM_FLAG[DB_HOST]:-}" && "${DB_HOST:-}" == postgres ]]; then
    DB_HOST=""
    unset 'FROM_ENV[DB_HOST]'
fi

if [[ "$REDIS_MODE" == bundled ]]; then
    REDIS_HOST=redis
    REDIS_PORT=6379
elif [[ -z "${FROM_FLAG[REDIS_HOST]:-}" && "${REDIS_HOST:-}" == redis ]]; then
    REDIS_HOST=""
    unset 'FROM_ENV[REDIS_HOST]'
fi

if [[ "$TYPESENSE_MODE" == bundled ]]; then
    TYPESENSE_HOST=typesense
    TYPESENSE_PORT=8108
    TYPESENSE_PROTOCOL=http
elif [[ -z "${FROM_FLAG[TYPESENSE_HOST]:-}" && "${TYPESENSE_HOST:-}" == typesense ]]; then
    TYPESENSE_HOST=""
    unset 'FROM_ENV[TYPESENSE_HOST]'
fi

if [[ "$DB_MODE" != bundled ]]; then
    ask DB_HOST "PostgreSQL host or container name" required
    ask DB_PORT "PostgreSQL port"
fi
if [[ "$REDIS_MODE" != bundled ]]; then
    ask REDIS_HOST "Redis host or container name" required
    ask REDIS_PORT "Redis port"
    ask REDIS_USERNAME "Redis ACL username (optional, Enter for none)"
fi
if [[ "$TYPESENSE_MODE" != bundled ]]; then
    ask TYPESENSE_HOST "Typesense host or container name" required
    ask TYPESENSE_PORT "Typesense port"
    ask TYPESENSE_PROTOCOL "Typesense protocol (http or https)"
fi

if [[ "$DB_MODE" != bundled ]]; then
    ask_secret POSTGRES_PASSWORD "PostgreSQL password"
fi
if [[ "$REDIS_MODE" != bundled ]]; then
    ask_secret REDIS_PASSWORD "Redis password (optional, Enter for none)"
fi
if [[ "$TYPESENSE_MODE" != bundled ]]; then
    ask_secret TYPESENSE_API_KEY "Typesense API key" required
fi

if [[ "$DB_MODE" == container || "$REDIS_MODE" == container || "$TYPESENSE_MODE" == container ]]; then
    ask DATASTORE_NETWORK "Docker network the datastore container(s) are on" required
fi

# ── Images ───────────────────────────────────────────────────────────────────
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

# ── Derived topology ─────────────────────────────────────────────────────────
if [[ "$NO_TLS" == true ]]; then
    PUBLIC_SCHEME=http
    SESSION_SECURE_COOKIE=false
    CADDY_XFP_PORT=80
    NGINX_XFP_PORT=80
else
    PUBLIC_SCHEME=https
    SESSION_SECURE_COOKIE=true
    CADDY_XFP_PORT=$PUBLIC_PORT
    if [[ "$EDGE_MODE" == nginx ]]; then
        NGINX_XFP_PORT=$HTTPS_PORT
    else
        NGINX_XFP_PORT=$PUBLIC_PORT
    fi
fi

CADDYFILE=./Caddyfile
CADDY_GLOBAL_OPTIONS=""
CADDY_SITE_ADDRESS_PREFIX=""
CADDY_TRUSTED_PROXIES=""

if [[ "$ROUTER" == caddy ]]; then
    if [[ "$EDGE_MODE" == caddy ]]; then
        CADDYFILE=./Caddyfile
        if [[ "$NO_TLS" != true ]]; then
            CADDY_GLOBAL_OPTIONS="email ${ACME_EMAIL}"
        fi
    else
        CADDYFILE=./Caddyfile.behind-edge
        CADDY_TRUSTED_PROXIES="$EDGE_PROXIES"
    fi
fi

if [[ "$SKIP_ADMIN" == true ]]; then
    CADDY_ADMIN_SITE="http://admin-disabled.invalid"
    CADDY_ADMIN_UPSTREAM="127.0.0.1:1"
elif [[ "$ROUTER" == caddy && "$EDGE_MODE" != caddy ]]; then
    CADDY_ADMIN_SITE="http://${ADMIN_DOMAIN}"
    CADDY_ADMIN_UPSTREAM="admin:80"
else
    CADDY_ADMIN_SITE="${CADDY_SITE_ADDRESS_PREFIX}${ADMIN_DOMAIN}"
    CADDY_ADMIN_UPSTREAM="admin:80"
fi

COMPOSE_FILES="docker-compose.yml"
if [[ "$ROUTER" == caddy ]]; then
    if [[ "$EDGE_MODE" == caddy ]]; then
        COMPOSE_FILES+=":docker-compose.caddy.yml"
    else
        COMPOSE_FILES+=":docker-compose.caddy-behind-edge.yml"
    fi
fi
if [[ "$DB_MODE" == container || "$REDIS_MODE" == container || "$TYPESENSE_MODE" == container ]]; then
    COMPOSE_FILES+=":docker-compose.external-net.yml"
fi

PROFILES=()
[[ "$ROUTER" == caddy ]] && PROFILES+=(caddy)
[[ "$SKIP_ADMIN" != true ]] && PROFILES+=(admin)
[[ "$DB_MODE" == bundled ]] && PROFILES+=(db-bundled)
[[ "$REDIS_MODE" == bundled ]] && PROFILES+=(redis-bundled)
[[ "$TYPESENSE_MODE" == bundled ]] && PROFILES+=(typesense-bundled)
COMPOSE_PROFILES="$(IFS=,; printf '%s' "${PROFILES[*]}")"

TRUSTED_PROXIES="*"
CORS_ALLOWED_ORIGINS="${PUBLIC_SCHEME}://${API_DOMAIN},${PUBLIC_SCHEME}://${SHOP_DOMAIN}"
if [[ "$SKIP_ADMIN" != true ]]; then
    CORS_ALLOWED_ORIGINS+=",${PUBLIC_SCHEME}://${ADMIN_DOMAIN}"
fi

# Nginx settings (rendered only when the router is nginx).
NGINX_TEMPLATE=""
NGINX_XFP_PROTO="https"
NGINX_TLS_CERT=""
NGINX_TLS_KEY=""
NGINX_LISTEN_TLS=""
NGINX_HTTP2_LINE=""
if [[ "$ROUTER" == nginx ]]; then
    if [[ "$EDGE_MODE" == nginx ]]; then
        NGINX_TEMPLATE="jedu-tls.conf.template"
        NGINX_TLS_CERT="${NGINX_TLS_CERT:-/etc/letsencrypt/live/${API_DOMAIN}/fullchain.pem}"
        NGINX_TLS_KEY="${NGINX_TLS_KEY:-/etc/letsencrypt/live/${API_DOMAIN}/privkey.pem}"
        ask NGINX_TLS_CERT "TLS certificate chain path" required
        ask NGINX_TLS_KEY "TLS private key path" required
        NGINX_LISTEN_TLS="${HTTP_BIND}:${HTTPS_PORT} ssl;"
        if nginx_supports_http2_directive; then
            NGINX_HTTP2_LINE="http2 on;"
        else
            NGINX_LISTEN_TLS="${HTTP_BIND}:${HTTPS_PORT} ssl http2;"
            NGINX_HTTP2_LINE=""
        fi
    else
        NGINX_TEMPLATE="jedu-http.conf.template"
        if [[ "$NO_TLS" == true ]]; then
            NGINX_XFP_PROTO="http"
        else
            NGINX_XFP_PROTO='$http_x_forwarded_proto'
        fi
        NGINX_LISTEN_TLS=""
        NGINX_HTTP2_LINE=""
    fi
fi

if [[ "$ROUTER" == nginx ]]; then
    NGINX_CONFIG_NAME="${NGINX_CONFIG_NAME:-jedu-docker.conf}"
    if [[ -d /etc/nginx/sites-enabled ]]; then
        NGINX_DEST="/etc/nginx/sites-available/$NGINX_CONFIG_NAME"
        NGINX_LINK="/etc/nginx/sites-enabled/$NGINX_CONFIG_NAME"
    else
        NGINX_DEST="/etc/nginx/conf.d/$NGINX_CONFIG_NAME"
        NGINX_LINK=""
    fi
fi

# Secrets are generated only when absent, which is what makes re-running safe.
gen_if_empty APP_KEY gen_app_key
gen_if_empty STOREFRONT_REVALIDATE_SECRET gen_secret 32
gen_if_empty HORIZON_PASSWORD gen_secret 16
if [[ "$DB_MODE" == bundled ]]; then
    gen_if_empty POSTGRES_PASSWORD gen_secret 24
fi
if [[ "$REDIS_MODE" == bundled ]]; then
    gen_if_empty REDIS_PASSWORD gen_secret 24
fi
if [[ "$TYPESENSE_MODE" == bundled ]]; then
    gen_if_empty TYPESENSE_API_KEY gen_secret 24
fi

# External datastores still need a coordinate; refuse a half-configured target.
case "$DB_MODE:$DB_HOST" in
    external:|container:) log_error "PostgreSQL mode is ${DB_MODE} but DB_HOST is empty."; exit 1 ;;
esac
case "$REDIS_MODE:$REDIS_HOST" in
    external:|container:) log_error "Redis mode is ${REDIS_MODE} but REDIS_HOST is empty."; exit 1 ;;
esac
case "$TYPESENSE_MODE:$TYPESENSE_HOST" in
    external:|container:) log_error "Typesense mode is ${TYPESENSE_MODE} but TYPESENSE_HOST is empty."; exit 1 ;;
esac
if [[ "$DB_MODE" != bundled && -z "${POSTGRES_PASSWORD:-}" ]]; then
    log_warn "PostgreSQL mode is ${DB_MODE} but no password is set; the API will only connect if that server allows it."
fi
if [[ "$DB_MODE" == bundled && -z "${POSTGRES_PASSWORD:-}" ]]; then
    log_error "A bundled PostgreSQL cannot start without POSTGRES_PASSWORD."
    exit 1
fi
if [[ "$REDIS_MODE" == bundled && -z "${REDIS_PASSWORD:-}" ]]; then
    log_error "A bundled Redis cannot start without REDIS_PASSWORD."
    exit 1
fi

# Port checks. A re-run may keep its own ports; a foreign listener is fatal.
if [[ "$EDGE_MODE" == caddy ]]; then
    require_port "$HTTP_PORT" "Caddy HTTP"
    require_port "$HTTPS_PORT" "Caddy HTTPS"
elif [[ "$ROUTER" == caddy ]]; then
    require_port "$HTTP_PORT" "Caddy HTTP (edge=external publishes only this)"
fi
require_port "$API_LOOPBACK_PORT" "api loopback"
require_port "$SHOP_LOOPBACK_PORT" "shop loopback"
[[ "$SKIP_ADMIN" != true ]] && require_port "$ADMIN_LOOPBACK_PORT" "admin loopback"

if [[ "$DB_MODE" == container || "$REDIS_MODE" == container || "$TYPESENSE_MODE" == container ]]; then
    docker network inspect "$DATASTORE_NETWORK" >/dev/null 2>&1 || {
        log_error "Docker network '${DATASTORE_NETWORK}' does not exist."
        log_error "Create or identify the network the datastore containers are on, e.g.:"
        log_error "  docker network ls"
        exit 1
    }
    log_success "Datastore network ${DATASTORE_NETWORK} exists."
fi

check_dns

# ── Plan ─────────────────────────────────────────────────────────────────────
log_step "Plan"
cat <<EOF
  Project            ${COMPOSE_PROJECT}  ($SCRIPT_DIR)
  Edge / TLS         ${EDGE_MODE}$([[ "$NO_TLS" == true ]] && echo " (public http)")
  Router             ${ROUTER}   compose files: ${COMPOSE_FILES}
  API                ${PUBLIC_SCHEME}://${API_DOMAIN}       ${JEDU_API_IMAGE}
  Storefront         ${PUBLIC_SCHEME}://${SHOP_DOMAIN}       ${JEDU_SHOP_IMAGE}
  Admin              $(if [[ "$SKIP_ADMIN" == true ]]; then echo '(skipped)'; else echo "${PUBLIC_SCHEME}://${ADMIN_DOMAIN}       ${JEDU_ADMIN_IMAGE}"; fi)
  Persistent files   ${JEDU_DATA_DIR}
  PostgreSQL         $(if [[ "$DB_MODE" == bundled ]]; then echo "bundled (jedu-postgres)"; elif [[ "$DB_MODE" == external ]]; then echo "external ${DB_HOST}:${DB_PORT}"; else echo "container ${DB_HOST}:${DB_PORT} on ${DATASTORE_NETWORK}"; fi)
  Redis              $(if [[ "$REDIS_MODE" == bundled ]]; then echo "bundled (jedu-redis)"; elif [[ "$REDIS_MODE" == external ]]; then echo "external ${REDIS_HOST}:${REDIS_PORT}"; else echo "container ${REDIS_HOST}:${REDIS_PORT} on ${DATASTORE_NETWORK}"; fi)
  Typesense          $(if [[ "$TYPESENSE_MODE" == bundled ]]; then echo "bundled (jedu-typesense)"; elif [[ "$TYPESENSE_MODE" == external ]]; then echo "external ${TYPESENSE_HOST}:${TYPESENSE_PORT}"; else echo "container ${TYPESENSE_HOST}:${TYPESENSE_PORT} on ${DATASTORE_NETWORK}"; fi)
  Secrets            $(if [[ -f "$ENV_FILE" && "$RESET_ENV" != true ]]; then echo 'kept from the existing .env'; else echo 'newly generated'; fi)
EOF

if [[ "$INTERACTIVE" == true ]] && ! confirm "Write .env and start the stack?"; then
    log_warn "Aborted; nothing was changed."
    exit 0
fi

# ── Write .env ───────────────────────────────────────────────────────────────
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
set_env EDGE_MODE "$EDGE_MODE"
set_env ROUTER "$ROUTER"
set_env EDGE_PROXIES "${EDGE_PROXIES:-}"
set_env COMPOSE_FILE "$COMPOSE_FILES"
set_env COMPOSE_PROFILES "$COMPOSE_PROFILES"
set_env TRUSTED_PROXIES "$TRUSTED_PROXIES"
set_env CORS_ALLOWED_ORIGINS "$CORS_ALLOWED_ORIGINS"
set_env CORS_SUPPORTS_CREDENTIALS true
set_env CORS_MAX_AGE "${CORS_MAX_AGE:-0}"
set_env JEDU_DATA_DIR "$JEDU_DATA_DIR"
set_env CADDYFILE "$CADDYFILE"
set_env CADDY_GLOBAL_OPTIONS "$CADDY_GLOBAL_OPTIONS"
set_env CADDY_SITE_ADDRESS_PREFIX "$CADDY_SITE_ADDRESS_PREFIX"
set_env CADDY_ADMIN_SITE "$CADDY_ADMIN_SITE"
set_env CADDY_ADMIN_UPSTREAM "$CADDY_ADMIN_UPSTREAM"
set_env CADDY_TRUSTED_PROXIES "$CADDY_TRUSTED_PROXIES"
set_env CADDY_XFP_PORT "$CADDY_XFP_PORT"
set_env CADDY_BIND "${CADDY_BIND:-0.0.0.0}"
set_env CADDY_IMAGE "$CADDY_IMAGE"
set_env HTTP_PORT "$HTTP_PORT"
set_env HTTPS_PORT "$HTTPS_PORT"
set_env PUBLIC_PORT "$PUBLIC_PORT"
set_env HTTP_BIND "$HTTP_BIND"
set_env NGINX_CONFIG_NAME "$NGINX_CONFIG_NAME"
set_env API_LOOPBACK_PORT "$API_LOOPBACK_PORT"
set_env SHOP_LOOPBACK_PORT "$SHOP_LOOPBACK_PORT"
set_env ADMIN_LOOPBACK_PORT "$ADMIN_LOOPBACK_PORT"
set_env NGINX_TLS_CERT "${NGINX_TLS_CERT:-}"
set_env NGINX_TLS_KEY "${NGINX_TLS_KEY:-}"
set_env DB_MODE "$DB_MODE"
set_env DB_HOST "$DB_HOST"
set_env DB_PORT "$DB_PORT"
set_env REDIS_MODE "$REDIS_MODE"
set_env REDIS_HOST "$REDIS_HOST"
set_env REDIS_PORT "$REDIS_PORT"
set_env REDIS_USERNAME "${REDIS_USERNAME:-}"
set_env REDIS_PASSWORD "${REDIS_PASSWORD:-}"
set_env TYPESENSE_MODE "$TYPESENSE_MODE"
set_env TYPESENSE_HOST "$TYPESENSE_HOST"
set_env TYPESENSE_PORT "$TYPESENSE_PORT"
set_env TYPESENSE_PROTOCOL "$TYPESENSE_PROTOCOL"
set_env TYPESENSE_API_KEY "${TYPESENSE_API_KEY:-}"
set_env DATASTORE_NETWORK "${DATASTORE_NETWORK:-jedu-datastores}"
set_env JEDU_API_IMAGE "$JEDU_API_IMAGE"
set_env JEDU_SHOP_IMAGE "$JEDU_SHOP_IMAGE"
set_env JEDU_ADMIN_IMAGE "$JEDU_ADMIN_IMAGE"
set_env POSTGRES_MAX_LOCKS "$POSTGRES_MAX_LOCKS"
set_env POSTGRES_DB "$POSTGRES_DB"
set_env POSTGRES_USER "$POSTGRES_USER"
set_env POSTGRES_PASSWORD "${POSTGRES_PASSWORD:-}"
set_env REDIS_IMAGE "$REDIS_IMAGE"
set_env POSTGRES_IMAGE "$POSTGRES_IMAGE"
set_env TYPESENSE_IMAGE "$TYPESENSE_IMAGE"
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
set_env MAIL_HOST "$MAIL_HOST"
set_env MAIL_PORT "$MAIL_PORT"
set_env MAIL_USERNAME "$MAIL_USERNAME"
set_env MAIL_PASSWORD "$MAIL_PASSWORD"
set_env MAIL_SCHEME "$MAIL_SCHEME"
set_env MAIL_FROM_ADDRESS "$MAIL_FROM_ADDRESS"
set_env MAIL_FROM_NAME "$MAIL_FROM_NAME"
set_env SENTRY_LARAVEL_DSN "${SENTRY_LARAVEL_DSN:-}"
set_env SENTRY_ENVIRONMENT "${SENTRY_ENVIRONMENT:-production}"
chmod 600 "$ENV_WORK"
mv "$ENV_WORK" "$ENV_FILE"
log_success "Wrote $(basename "$ENV_FILE") (mode 600)."

log_step "Preparing persistent data directory"
prepare_data_directories
log_success "Persistent files will be stored under ${JEDU_DATA_DIR}."

log_step "Validating the compose file"
compose config --quiet
log_success "Compose configuration resolves cleanly."

# ── Host nginx ───────────────────────────────────────────────────────────────
NGINX_APPLIED=false
if [[ "$ROUTER" == nginx ]]; then
    log_step "Preparing the host nginx configuration"
    render_nginx "$NGINX_TEMPLATE"
    log_success "Rendered ${NGINX_RENDERED#"$SCRIPT_DIR"/}."
    apply_nginx
fi

# ── Registry ─────────────────────────────────────────────────────────────────
pull_list=("$JEDU_API_IMAGE" "$JEDU_SHOP_IMAGE")
[[ "$ROUTER" == caddy ]] && pull_list+=("$CADDY_IMAGE")
[[ "$SKIP_ADMIN" != true ]] && pull_list+=("$JEDU_ADMIN_IMAGE")
[[ "$DB_MODE" == bundled ]] && pull_list+=("$POSTGRES_IMAGE")
[[ "$REDIS_MODE" == bundled ]] && pull_list+=("$REDIS_IMAGE")
[[ "$TYPESENSE_MODE" == bundled ]] && pull_list+=("$TYPESENSE_IMAGE")

if [[ "$SKIP_PULL" == true ]]; then
    log_warn "Skipping image pull (--skip-pull/--no-pull)."
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

    failed=()
    for image in "${pull_list[@]}"; do
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
            log_error "Check that the configured admin image tag exists in Nexus, or rerun with --skip-admin."
        fi
        log_error "Retry the pull by hand to see the registry's error:  docker pull ${failed[0]}"
        exit 1
    fi
fi

# ── Up ───────────────────────────────────────────────────────────────────────
log_step "Starting the stack"
compose up -d --remove-orphans

log_step "Waiting for the datastores"
[[ "$DB_MODE" == bundled ]] && { wait_for_health jedu-postgres "PostgreSQL" 180 || true; }
[[ "$REDIS_MODE" == bundled ]] && { wait_for_health jedu-redis "Redis" 120 || true; }
[[ "$TYPESENSE_MODE" == bundled ]] && { wait_for_health jedu-typesense "Typesense" 180 || true; }

log_step "Waiting for the API"
if wait_for_health jedu-api "the API" "$HEALTH_TIMEOUT"; then
    if [[ "$INTERACTIVE" == true ]]; then
        log_step "Initial admin staff account"
        if compose exec -T api php artisan list --raw 2>/dev/null | grep -Fxq 'staff:create-first-admin'; then
            if ! compose exec api php artisan staff:create-first-admin; then
                log_warn "Could not create the first admin staff account. Retry after the API is healthy:"
                log_warn "  docker compose exec api php artisan staff:create-first-admin"
            fi
        else
            log_warn "The API image does not include staff:create-first-admin. Publish and deploy an image containing this command, then run:"
            log_warn "  docker compose exec api php artisan staff:create-first-admin"
        fi
    else
        log_warn "No interactive terminal detected; skipping the first-admin prompt."
        log_warn "Run this after deploying an API image with the command:"
        log_warn "  docker compose exec api php artisan staff:create-first-admin"
    fi

    # The engine setting is stored in the database, so it follows a fresh volume
    # rather than the image; deploy.php makes the same call on every deploy.
    if compose exec -T api php artisan pgroonga:engine typesense >/dev/null 2>&1; then
        log_success "Search engine set to Typesense."
    else
        log_warn "Could not set the search engine to Typesense. Run it by hand once the API is healthy:"
        log_warn "  docker compose exec api php artisan pgroonga:engine typesense"
        if [[ "$DB_MODE" != bundled ]]; then
            log_warn "An external PostgreSQL must have the pgroonga extension available: the"
            log_warn "first migration runs CREATE EXTENSION pgroonga and the role needs rights to it."
        fi
    fi
fi
wait_for_health jedu-worker "the queue worker" 120 || true
if [[ "$SKIP_ADMIN" != true ]]; then
    wait_for_health jedu-admin "the admin panel" 60 || true
fi
wait_for_health jedu-shop "the storefront" 120 || true
[[ "$ROUTER" == caddy ]] && { wait_for_health jedu-caddy "Caddy" 60 || true; }

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

  Topology       edge=${EDGE_MODE} router=${ROUTER}
  Compose        ${COMPOSE_FILES}
  Profiles       ${COMPOSE_PROFILES}
  Datastores     postgres=${DB_MODE} redis=${REDIS_MODE} typesense=${TYPESENSE_MODE}
EOF

if [[ "$ROUTER" == nginx ]]; then
    if [[ "$NGINX_APPLIED" == true ]]; then
        echo "  nginx          ${NGINX_DEST} (installed and reloaded)"
    else
        echo "  nginx          NOT APPLIED — install ${NGINX_RENDERED#"$SCRIPT_DIR"/} manually:"
        echo "                 sudo cp ${NGINX_RENDERED#"$SCRIPT_DIR"/} ${NGINX_DEST}"
        [[ -n "${NGINX_LINK:-}" ]] && echo "                 sudo ln -sfn ${NGINX_DEST} ${NGINX_LINK}"
        echo "                 sudo nginx -t && sudo systemctl reload nginx"
    fi
fi

cat <<EOF

  Status         docker compose ps
  Logs           docker compose logs -f api
  Shell          docker compose exec api bash
  Stop           docker compose down          (add -v to also delete every volume)

  Before announcing this deployment, work through the go-live checklist in
  README.md: mail delivery, client-IP verification, DNS/TLS verification and a
  database backup.

  This directory is independent of the Deployer release in /var/www: it never
  touches that tree, its shared .env or php8.4-fpm.
EOF
