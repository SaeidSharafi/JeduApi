# Worktrees

Default to the primary checkout on `main`. Create a worktree when the user asks for one, when a
skill the user invoked requires it — `/implement-spec` gives every implementer subagent its own,
and the Orca flows (`orca-cli`, `orchestration`) create them by design — or after they approve
one. Otherwise ask first. Never keep the same change in two trees: read-before-edit is tracked
per absolute path, so the second copy of a file must be read before it can be edited.

When several worktrees are needed at once (an `/implement-spec` fan-out), provision them one at
a time. Each one is a full Sail stack — its own pgsql, Redis and Typesense containers — so keep
the count small, and never pipe `worktree-setup.sh` through `tail`: that hides a non-zero exit
and reports a worktree that never started. The script runs `composer install` against the shared
host composer cache, so concurrent provisioning races.

Create a worktree:

    git worktree add .worktrees/<name> -b <branch> <base>
    scripts/worktree-setup.sh .worktrees/<name>

`scripts/worktree-setup.sh` copies `.env` from the primary checkout, allocates the
worktree's port block and Compose project name, links the Typesense model cache, runs
`composer install`, starts Sail and migrates. It is idempotent and must exit 0 before any
work starts in that worktree.

Remove a worktree:

    scripts/worktree-teardown.sh .worktrees/<name>

Do not run those setup steps by hand, do not copy `docker/typesense-data` between
worktrees, and do not delete a worktree directory by hand.

Git-dependent tooling fails inside a worktree's Sail container: the worktree's `.git` file
points at the primary checkout, which is outside the bind mount, so `pint --dirty` cannot see
the diff. Pass the files explicitly instead:

    git diff --name-only main...HEAD -- '*.php' | xargs vendor/bin/sail bin pint --format agent
