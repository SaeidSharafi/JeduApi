#!/usr/bin/env bash
#
# Jedu - CrowdSec log-processing whitelist (s02-enrich parser).
#
# This does not touch the WAF: it stops the access-log scenarios (http-probing,
# http-crawl-non_statics, http-bf, ...) from banning legitimate traffic on the
# endpoints below. The in-band WAF allow-list lives in
# crowdsec-appsec-whitelist.sh.
#
# The paths are shared with every app behind this CrowdSec instance - keep the
# generic ones (/payment/, /app/, ...) even if this project only needs the
# /api/v1/... ones.
#
# Notes on the expressions:
#   * `evt.Meta.http_path` includes the query string for both the Caddy and the
#     nginx parsers, so always match with `startsWith`, never `==`.
#   * Request headers are not part of a log event: the `Upgrade: websocket`
#     check cannot live here, the handshake is matched by path instead.
#   * `evt.Meta.http_verb` is set by both parsers; a missing verb makes the
#     whole entry false, which is the safe direction.
#
# Usage: sudo ./crowdsec-parser-whitelist.sh {--apply | --revert}
set -euo pipefail

CROWDSEC_DIR="${CROWDSEC_DIR:-/etc/crowdsec}"
TARGET_FILE="${CROWDSEC_DIR}/parsers/s02-enrich/jedu-apps-whitelist.yaml"
BACKUP_FILE="${TARGET_FILE}.bak"

log() { printf '===> %s\n' "$*"; }
warn() { printf 'WARN: %s\n' "$*" >&2; }

reload_crowdsec() {
    if systemctl is-active --quiet crowdsec 2>/dev/null; then
        log "Reloading crowdsec (systemd)"
        systemctl reload crowdsec 2>/dev/null || systemctl restart crowdsec
        return 0
    fi

    local names=""
    names="$(docker ps --format '{{.Names}}' 2>/dev/null || true)" || true
    if [ -n "${names}" ] && grep -q 'crowdsec' <<<"${names}"; then
        local container=""
        container="$(docker ps -q -f name=crowdsec 2>/dev/null | head -n 1)" || true
        if [ -n "${container}" ]; then
            log "Restarting crowdsec container ${container} (docker)"
            docker restart "${container}" >/dev/null
            return 0
        fi
    fi

    warn "crowdsec is neither a systemd unit nor a running container - reload it yourself"
    return 0
}

validate_crowdsec() {
    if ! command -v crowdsec >/dev/null 2>&1; then
        warn "'crowdsec' is not in PATH - skipping configuration test"
        return 0
    fi

    local out=""
    if out="$(crowdsec -t 2>&1)"; then
        return 0
    fi

    printf '%s\n' "${out}" >&2
    return 1
}

case "${1:-}" in
    --apply)
        log "[1/4] Writing the jedu-apps whitelist parser"
        mkdir -p "$(dirname "${TARGET_FILE}")"
        # Keep the pristine pre-first-apply copy: re-applying must not overwrite
        # it, and our own generated file must never seed it (apply -> apply ->
        # revert has to remove the whitelist, not restore it).
        if [ -f "${TARGET_FILE}" ] && [ ! -f "${BACKUP_FILE}" ] \
            && ! grep -qE '^name:[[:space:]]*jedu/' "${TARGET_FILE}"; then
            cp -p "${TARGET_FILE}" "${BACKUP_FILE}"
        fi

        cat <<'YAML' > "${TARGET_FILE}"
name: jedu/apps-advanced-whitelist
description: "Whitelist bank callbacks, websocket handshakes, public forms, deploy webhook and search autocomplete for the jedu.ir ecosystem"
filter: "evt.Meta.service == 'http' && evt.Meta.log_type in ['http_access-log', 'http_error-log']"
whitelist:
  reason: "Legitimate payment callbacks, websockets, form posts and autocomplete traffic"
  expression:
    - "evt.Meta.http_path startsWith '/storage/'"
    # 1. Payment gateway callbacks. The bank redirects or POSTs the customer
    #    back with RefId/ResCode/SaleReferenceId/... in the query string.
    #    The precise Jedu route comes first; the generic ones cover the other
    #    apps (Zarinpal/Saman/Mellat legacy callbacks).
    - "evt.Meta.http_path startsWith '/api/v1/shop/payment/gateway/callback'"
    - "evt.Meta.http_path startsWith '/payment/'"
    - "evt.Meta.http_path startsWith '/api/payment/'"

    # 2. Soketi/Pusher WebSocket handshakes: /app/{key} and /apps/{id}/events.
    #    The Upgrade header is not visible to the log pipeline, so match paths.
    - "evt.Meta.http_path startsWith '/app/'"curl -Iv https://api.jedu.ir --resolve api.jedu.ir:443:
    - "evt.Meta.http_path startsWith '/apps/'"

    # 3. Public rate-limited form posts (Persian free text, CRS/libinjection
    #    false positives, and repeated submits from the same office IP).
    - "evt.Meta.http_verb == 'POST' && (evt.Meta.http_path startsWith '/api/v1/shop/contact-us' || evt.Meta.http_path startsWith '/api/v1/shop/collaboration' || evt.Meta.http_path startsWith '/api/v1/shop/advice-requests' || evt.Meta.http_path startsWith '/api/v1/shop/organization/training-requests')"

    # 4. Checkout and wallet top-up: the last hop before the gateway redirect,
    #    a ban here loses the sale.
    - "evt.Meta.http_verb == 'POST' && (evt.Meta.http_path startsWith '/api/v1/shop/checkout' || evt.Meta.http_path startsWith '/api/v1/shop/wallet/topup')"

    # 5. Authenticated student area: review submissions, quiz/Moodle SSO,
    #    downloads (slow requests used to trip the crawl scenarios).
    - "evt.Meta.http_path startsWith '/api/v1/shop/student/'"

    # 6. Search autocomplete: one request per keystroke, the classic
    #    http-crawl-non_statics false positive.
    - "evt.Meta.http_path startsWith '/api/v1/shop/search'"

    # 7. Forgejo -> API deploy webhook (signature-verified by spatie/webhook-client).
    - "evt.Meta.http_verb == 'POST' && evt.Meta.http_path startsWith '/api/v1/webhooks/github-deployer'"

    # 8. Health probe used by the deploy healthcheck and uptime monitors.
    - "evt.Meta.http_path == '/up'"
YAML

        log "[2/4] Validating CrowdSec configuration"
        if ! validate_crowdsec; then
            warn "CrowdSec rejected the parser - rolling back"
            if [ -f "${BACKUP_FILE}" ]; then
                mv -f "${BACKUP_FILE}" "${TARGET_FILE}"
            else
                rm -f "${TARGET_FILE}"
            fi
            exit 1
        fi

        log "[3/4] Reloading CrowdSec"
        reload_crowdsec

        log "[4/4] Done"
        echo "SUCCESS: jedu-apps whitelist applied and CrowdSec reloaded"
        ;;

    --revert)
        log "Reverting CrowdSec whitelist"
        if [ -f "${BACKUP_FILE}" ]; then
            mv -f "${BACKUP_FILE}" "${TARGET_FILE}"
        else
            rm -f "${TARGET_FILE}"
        fi

        validate_crowdsec || warn "configuration test failed after revert - check ${CROWDSEC_DIR}"
        reload_crowdsec
        echo "SUCCESS: CrowdSec configuration reverted"
        ;;

    *)
        echo "Usage: sudo $0 {--apply | --revert}"
        exit 1
        ;;
esac
