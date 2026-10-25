#!/usr/bin/env bash
#
# Jedu - CrowdSec AppSec (WAF) allow-list for trusted public endpoints.
#
# Why an appsec-config and not an appsec-rule:
#   AppSec *rule* files can only detect/block. There is no `allow` action in a
#   rule (see https://docs.crowdsec.net/docs/next/appsec/rules_syntax/), so the
#   only supported way to keep the WAF from blocking a request is an
#   appsec-config with an in-band `on_match` hook that cancels the alert/event
#   and downgrades the remediation to "allow".
#
#   That config must be registered in the AppSec datasource
#   (`appsec_configs:` in the acquisition), otherwise CrowdSec never loads it.
#   This script writes the config, wires it in, validates and reloads.
#
# The paths below are shared with every app behind this CrowdSec instance
# (storefront, admin, websocket server) - keep them generic.
#
# Usage: sudo ./crowdsec-appsec-whitelist.sh {--apply | --revert}
set -euo pipefail

CROWDSEC_DIR="${CROWDSEC_DIR:-/etc/crowdsec}"
CONFIG_NAME="jedu/appsec-exclusions"
CONFIG_FILE="${CROWDSEC_DIR}/appsec-configs/jedu-appsec-exclusions.yaml"
BACKUP_SUFFIX=".jedu.bak"
LEGACY_RULE_FILE="${CROWDSEC_DIR}/appsec-rules/jedu-appsec-exclusions.yaml"

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

find_acquisition_files() {
    local f
    for f in \
        "${CROWDSEC_DIR}/acquis.yaml" \
        "${CROWDSEC_DIR}/acquis.yml" \
        "${CROWDSEC_DIR}"/acquis.d/*.yaml \
        "${CROWDSEC_DIR}"/acquis.d/*.yml; do
        [ -f "${f}" ] || continue
        grep -qE '^[[:space:]]*appsec_configs?:' "${f}" 2>/dev/null || continue
        printf '%s\n' "${f}"
    done
}

find_wired_acquisition_files() {
    local f
    for f in \
        "${CROWDSEC_DIR}/acquis.yaml" \
        "${CROWDSEC_DIR}/acquis.yml" \
        "${CROWDSEC_DIR}"/acquis.d/*.yaml \
        "${CROWDSEC_DIR}"/acquis.d/*.yml; do
        [ -f "${f}" ] || continue
        grep -q "${CONFIG_NAME}" "${f}" 2>/dev/null || continue
        printf '%s\n' "${f}"
    done
}

# Returns 0 when the file is (now) wired, 1 when its shape is not recognised.
wire_acquisition() {
    local file="$1"

    if grep -q "${CONFIG_NAME}" "${file}"; then
        return 0
    fi

    [ -f "${file}${BACKUP_SUFFIX}" ] || cp -p "${file}" "${file}${BACKUP_SUFFIX}"

    if grep -qE '^[[:space:]]*appsec_configs:[[:space:]]*$' "${file}"; then
        # Block list: ours first, so the configs that follow still own
        # default_remediation / default_pass_action / *_http_code.
        sed -i -E "s|^([[:space:]]*)appsec_configs:[[:space:]]*\$|\1appsec_configs:\n\1  - ${CONFIG_NAME}|" "${file}"
    elif grep -qE '^[[:space:]]*appsec_configs:[[:space:]]*\[' "${file}"; then
        # Inline flow list: ours first, same reason as the block form.
        sed -i -E "s|^([[:space:]]*appsec_configs:[[:space:]]*\[)|\1${CONFIG_NAME}, |" "${file}"
        sed -i -E 's|(^[[:space:]]*appsec_configs:[^]]*),[[:space:]]*\]|\1]|' "${file}"
    elif grep -qE '^[[:space:]]*appsec_config:[[:space:]]*[^[:space:]]' "${file}"; then
        # Single string form (older acquisition): convert to the list form.
        sed -i -E "s|^([[:space:]]*)appsec_config:[[:space:]]*([^[:space:]]+)[[:space:]]*\$|\1appsec_configs:\n\1  - ${CONFIG_NAME}\n\1  - \2|" "${file}"
    else
        rm -f "${file}${BACKUP_SUFFIX}"
        return 1
    fi

    return 0
}

restore_acquisition() {
    local file
    while IFS= read -r file; do
        [ -n "${file}" ] || continue
        if [ -f "${file}${BACKUP_SUFFIX}" ]; then
            mv -f "${file}${BACKUP_SUFFIX}" "${file}"
            log "  restored ${file}"
        else
            sed -i -E "\#^[[:space:]]*-[[:space:]]*${CONFIG_NAME}[[:space:]]*\$#d" "${file}"
            log "  removed ${CONFIG_NAME} from ${file}"
        fi
    done < <(find_wired_acquisition_files)
}

restore_config() {
    if [ -f "${CONFIG_FILE}${BACKUP_SUFFIX}" ]; then
        mv -f "${CONFIG_FILE}${BACKUP_SUFFIX}" "${CONFIG_FILE}"
        log "  restored ${CONFIG_FILE}"
    elif [ -f "${CONFIG_FILE}" ]; then
        rm -f "${CONFIG_FILE}"
        log "  removed ${CONFIG_FILE}"
    fi
}

apply() {
    log "[1/4] Writing ${CONFIG_NAME} appsec-config"
    mkdir -p "$(dirname "${CONFIG_FILE}")"
    # Back up a *pre-existing, foreign* config only. Our own generated file must
    # never seed the backup, otherwise apply -> apply -> revert would "restore"
    # the allow-list instead of removing it.
    if [ -f "${CONFIG_FILE}" ] && [ ! -f "${CONFIG_FILE}${BACKUP_SUFFIX}" ] \
        && ! grep -qE '^name:[[:space:]]*jedu/' "${CONFIG_FILE}"; then
        cp -p "${CONFIG_FILE}" "${CONFIG_FILE}${BACKUP_SUFFIX}"
    fi

    cat <<'YAML' > "${CONFIG_FILE}"
# AppSec allow-list for trusted public endpoints: payment callbacks,
# websocket handshakes, public forms, checkout, search, deploy webhook.
# Shared by every app behind this CrowdSec instance.
#
# NOTE: an appsec-config has no `description` key (unlike rules/parsers) and
# CrowdSec parses it strictly - an unknown key aborts the whole agent. Only
# `name`, `inband`/`outofband` and the hook keys below are valid here.
name: jedu/appsec-exclusions
#
# No `inband_rules` on purpose: the hub config (crowdsecurity/appsec-default or
# your own) keeps loading the rules, this file only adds hooks.
#
# AppSec rules cannot allow a request, so the allow is done with an in-band
# on_match hook: the match is still evaluated (and debug-logged), but it
# creates no alert/event and the remediation becomes "allow".
#
# On CrowdSec >= 1.8 this can be replaced by
#   inband.pre_eval -> SkipProcessing("jedu-allowlist")
# which skips rule evaluation for those requests entirely.
inband:
  on_match:
    - filter: |
        req.URL.Path startsWith "/storage/" or
        req.URL.Path startsWith "/api/v1/shop/payment/gateway/callback" or
        req.URL.Path startsWith "/payment/" or
        req.URL.Path startsWith "/api/payment/" or
        req.URL.Path startsWith "/app/" or
        req.URL.Path startsWith "/apps/" or
        req.URL.Path startsWith "/api/v1/shop/search" or
        req.URL.Path startsWith "/api/v1/shop/student/" or
        (req.Method == "POST" and (
          req.URL.Path == "/api/v1/shop/checkout" or
          req.URL.Path == "/api/v1/shop/wallet/topup" or
          req.URL.Path == "/api/v1/shop/contact-us" or
          req.URL.Path == "/api/v1/shop/collaboration" or
          req.URL.Path == "/api/v1/shop/advice-requests" or
          req.URL.Path == "/api/v1/shop/organization/training-requests" or
          req.URL.Path == "/api/v1/webhooks/github-deployer"
        )) or
        (req.Method == "GET" and req.URL.Path == "/up")
      apply:
        - CancelEvent()
        - CancelAlert()
        - SetRemediation("allow")
YAML

    log "[2/4] Registering it in the AppSec acquisition entry"
    local wired=0 file
    while IFS= read -r file; do
        if wire_acquisition "${file}"; then
            log "  wired into ${file}"
            wired=1
        else
            warn "could not wire ${file} (unsupported appsec_config shape)"
        fi
    done < <(find_acquisition_files)

    if [ "${wired}" -eq 0 ]; then
        warn "no acquisition entry exposing appsec_config/appsec_configs was found."
        warn "The config stays on disk but is NOT loaded until you add it, e.g.:"
        warn "  appsec_configs:"
        warn "    - ${CONFIG_NAME}    # keep this one first"
        warn "    - crowdsecurity/appsec-default"
        warn "in the AppSec datasource of ${CROWDSEC_DIR}/acquis.d/ and re-run --apply."
        exit 1
    fi

    log "[3/4] Validating CrowdSec configuration"
    if ! validate_crowdsec; then
        warn "CrowdSec rejected the configuration - rolling back"
        restore_acquisition
        restore_config
        exit 1
    fi

    log "[4/4] Reloading CrowdSec"
    reload_crowdsec

    if [ -f "${LEGACY_RULE_FILE}" ]; then
        warn "legacy rule ${LEGACY_RULE_FILE} is still present; it has no allow action"
        warn "and is ignored unless an appsec-config lists it - safe to delete."
    fi

    echo "SUCCESS: AppSec allow-list is active"
}

revert() {
    log "Reverting AppSec allow-list"
    printf '  [1/3] unregistering %s\n' "${CONFIG_NAME}"
    restore_acquisition

    printf '  [2/3] removing %s\n' "${CONFIG_FILE}"
    restore_config

    printf '  [3/3] validating and reloading\n'
    validate_crowdsec || warn "configuration test failed after revert - check ${CROWDSEC_DIR}"
    reload_crowdsec

    echo "SUCCESS: AppSec allow-list reverted"
}

case "${1:-}" in
    --apply) apply ;;
    --revert) revert ;;
    *)
        echo "Usage: sudo $0 {--apply | --revert}"
        exit 1
        ;;
esac
