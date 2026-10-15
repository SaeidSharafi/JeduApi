#!/usr/bin/env bash
#
# Drop the per-process test databases (`testing_test_*`) left behind by a Pest
# run that was killed or overlapped by a second run.
#
# Usage:
#   scripts/reset-test-databases.sh
#
# Symptom it fixes: ~149 migration/table failures on the next run, because the
# per-process databases are half-migrated. The next `--parallel` run recreates
# them, so dropping is safe and loses only test data.
#
# `DROP DATABASE ... WITH (FORCE)` silently does nothing while a connection is
# still attached, so every backend is terminated first and the result is
# verified. See .ai/rules/tests.md.
set -Eeuo pipefail

if [[ ! -x ./vendor/bin/sail ]]; then
    printf 'reset-test-databases: run this from the repository root (no vendor/bin/sail found)\n' >&2
    exit 1
fi

env_value() {
    grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

db_user="$(env_value DB_USERNAME)"
db_user="${db_user:-sail}"

query() {
    # /dev/null stops docker exec from draining the stdin of an enclosing read loop.
    ./vendor/bin/sail exec -T pgsql psql -U "$db_user" -d postgres -tAc "$1" < /dev/null
}

databases="$(query "SELECT datname FROM pg_database WHERE datname LIKE 'testing_test_%' ORDER BY datname")"

if [[ -z "${databases//[[:space:]]/}" ]]; then
    printf 'No testing_test_* databases to drop.\n'
    exit 0
fi

dropped=0

while IFS= read -r database; do
    [[ -z "${database//[[:space:]]/}" ]] && continue

    query "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${database}' AND pid <> pg_backend_pid()" >/dev/null
    query "DROP DATABASE IF EXISTS \"${database}\"" >/dev/null
    printf 'Dropped %s\n' "$database"
    dropped=$((dropped + 1))
done <<< "$databases"

remaining="$(query "SELECT count(*) FROM pg_database WHERE datname LIKE 'testing_test_%'")"

printf 'Dropped %d testing_test_* database(s); %s remaining.\n' "$dropped" "${remaining:-unknown}"

if [[ "${remaining:-0}" != "0" ]]; then
    printf 'reset-test-databases: some databases survived; stop the running test process, then retry\n' >&2
    exit 1
fi
