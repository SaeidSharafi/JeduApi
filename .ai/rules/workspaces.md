# Worktrees

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
