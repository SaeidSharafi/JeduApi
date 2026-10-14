#!/usr/bin/env bash
#
# Provision a git worktree of this repository so it can run its own Sail stack
# and its own database, in parallel with the primary checkout and with other
# worktrees.
#
# Usage:
#   scripts/worktree-setup.sh <worktree-path> [workspace-name]
#
#   <worktree-path>   An existing git worktree of this repo, e.g. one made with
#                     `git worktree add .worktrees/<ticket> -b <branch> <base>`
#   [workspace-name]  Seed for this worktree's port block and Compose project
#                     name. Defaults to the worktree directory name, so two
#                     worktrees with different names never share ports.
#
# Idempotent: safe to re-run on an already provisioned worktree.
set -Eeuo pipefail

worktree="${1:-$(pwd)}"
workspace="${2:-${WORKSPACE_NAME:-$(basename "$worktree")}}"

if [[ ! -d "$worktree" ]]; then
    printf 'worktree-setup: %s is not a directory\n' "$worktree" >&2
    exit 1
fi

cd "$worktree"

# The primary checkout is the repository that owns the shared .git directory.
root="$(cd "$(git rev-parse --git-common-dir)/.." && pwd)"

if [[ ! -f "$root/.env" ]]; then
    printf 'worktree-setup: no .env in primary checkout %s\n' "$root" >&2
    exit 1
fi

# .env is gitignored and must differ per worktree, so start from the primary
# checkout's copy and then override everything that has to be unique.
if [[ ! -f .env ]]; then
    cp "$root/.env" .env
fi

composer install \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs

# Stable per-workspace port block.
offset=$(($(printf '%s' "$workspace" | cksum | awk '{print $1}') % 1000))
base_port=$((18000 + offset * 5))

set_env() {
    local key="$1" value="$2"

    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

set_env APP_PORT "$base_port"
set_env FORWARD_DB_PORT "$((base_port + 1))"
set_env FORWARD_REDIS_PORT "$((base_port + 2))"
set_env FORWARD_TYPESENSE_PORT "$((base_port + 3))"
set_env FORWARD_MAILPIT_PORT "$((base_port + 4))"
set_env FORWARD_MAILPIT_DASHBOARD_PORT "$((base_port + 5))"

# This worktree gets its own Compose project, so its containers and volumes are
# isolated from the primary checkout and from every other worktree.
set_env COMPOSE_PROJECT_NAME "jedu_${offset}"

# A compose project volume keeps Typesense's index out of the checkout. Its
# on-disk files are owned by the container's user, which would otherwise leave
# files we cannot delete when the worktree is torn down. The embedding models
# are the exception: they are read-only at query time and expensive to fetch,
# so they are shared from the primary checkout instead (see below).
set_env TYPESENSE_DATA_VOLUME "sail-typesense"

# Typesense loads auto-embedding models from $TYPESENSE_DATA_DIR/models and only
# writes them when a model is missing, so one shared cache serves every
# worktree. The primary checkout's ~1.5G cache is linked in rather than copied;
# docker-compose.yml mounts this path as its own bind mount because Docker does
# not follow symlinks inside a bind-mounted directory.
mkdir -p docker/typesense-data
if [[ -d "$root/docker/typesense-data/models" && ! -e docker/typesense-data/models ]]; then
    ln -sfn "$root/docker/typesense-data/models" docker/typesense-data/models
elif [[ ! -e docker/typesense-data/models ]]; then
    mkdir -p docker/typesense-data/models
    printf 'worktree-setup: %s has no Typesense model cache; models will be downloaded on first use\n' \
        "$root/docker/typesense-data/models" >&2
fi

./vendor/bin/sail up -d

if grep -q '^APP_KEY=$' .env; then
    ./vendor/bin/sail artisan key:generate --force
fi

# `sail up -d` returns before pgsql accepts connections.
for _ in $(seq 1 30); do
    if ./vendor/bin/sail artisan migrate --force >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

./vendor/bin/sail artisan migrate --force

printf 'Worktree %s is ready: ports from %s, compose project jedu_%s.\n' \
    "$workspace" "$base_port" "$offset"
