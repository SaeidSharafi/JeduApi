#!/usr/bin/env bash
#
# Run SQL against the local Sail database from the host.
#
# Usage:
#   scripts/db.sh "select count(*) from products"
#   scripts/db.sh -d testing "select 1"
#
# Connection details come from .env, so this also works inside a worktree.
# The query runs as psql inside the `pgsql` container — there is no host psql,
# which is why `sail psql` on its own prints nothing.
set -Eeuo pipefail

if [[ ! -x ./vendor/bin/sail ]]; then
    printf 'db: run this from the repository root (no vendor/bin/sail found)\n' >&2
    exit 1
fi

env_value() {
    grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

db_user="$(env_value DB_USERNAME)"
db_user="${db_user:-sail}"

database="$(env_value DB_DATABASE)"
database="${database:-jedushop}"

if [[ "${1:-}" == "-d" ]]; then
    database="${2:-$database}"
    shift 2
fi

if [[ $# -eq 0 ]]; then
    printf 'usage: scripts/db.sh [-d <database>] "<sql>"\n' >&2
    exit 1
fi

exec ./vendor/bin/sail exec -T pgsql psql -U "$db_user" -d "$database" -tA -c "$*" < /dev/null
