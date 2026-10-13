#!/usr/bin/env bash
#
# Tear down a provisioned worktree: stop its Sail stack, delete its Compose
# project's volumes, then remove the checkout and its branch.
#
# Usage:
#   scripts/worktree-teardown.sh <worktree-path>
#
# The branch is deleted with `git branch -d`, so a branch whose work has not
# been merged into its upstream is kept and reported instead of discarded.
set -Eeuo pipefail

worktree="${1:-}"

if [[ -z "$worktree" || ! -d "$worktree" ]]; then
    printf 'usage: scripts/worktree-teardown.sh <worktree-path>\n' >&2
    exit 1
fi

worktree="$(cd "$worktree" && pwd)"
root="$(cd "$(git -C "$worktree" rev-parse --git-common-dir)/.." && pwd)"

if [[ "$worktree" == "$root" ]]; then
    printf 'worktree-teardown: refusing to remove the primary checkout %s\n' "$root" >&2
    exit 1
fi

branch="$(git -C "$worktree" rev-parse --abbrev-ref HEAD)"

if [[ -x "$worktree/vendor/bin/sail" ]]; then
    (cd "$worktree" && ./vendor/bin/sail down --remove-orphans --volumes) ||
        printf 'worktree-teardown: sail down failed; containers or volumes may be left behind\n' >&2
fi

git -C "$root" worktree remove --force "$worktree"
git -C "$root" worktree prune

if [[ "$branch" != "HEAD" ]]; then
    if git -C "$root" branch -d "$branch" >/dev/null 2>&1; then
        printf 'Removed worktree %s and branch %s.\n' "$worktree" "$branch"
    else
        printf 'Removed worktree %s; kept branch %s because it is not merged.\n' \
            "$worktree" "$branch"
    fi
else
    printf 'Removed worktree %s (detached HEAD).\n' "$worktree"
fi
