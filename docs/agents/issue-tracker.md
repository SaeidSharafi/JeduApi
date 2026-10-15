# Issue tracker: Forgejo

Issues and specs for this repo live in Forgejo:

- Repository: `https://git.jedu.ir/kuro/Jedu-api`
- CLI: `fj` (forgejo-cli), already authenticated on this machine
- API base (fallback only): `https://git.jedu.ir/api/v1`
- GitHub is a mirror only; do not manage issues there.

## Use the `fj` CLI

Flags go **after** the subcommand. `-R origin` selects this repo's Forgejo remote, so it works
from the repository root without spelling out host or repo.

| Task | Command |
| --- | --- |
| Read an issue | `fj issue view -R origin <n>` |
| Read the thread | `fj issue view -R origin <n> comments` |
| List issues | `fj issue search -R origin` (`-s closed` / `-s all`, `-l bug,v1`, `"query"`) |
| Comment | `fj issue comment -R origin <n> "text"` or `--body-file <path>` |
| Close, with a comment | `fj issue close -R origin <n> -w "text"` |
| Create | `fj issue create -R origin "<title>" --body "text" --no-template` |
| Add / remove labels | `fj issue edit -R origin <n> labels -a <label>` / `-r <label>` (repeat per label) |
| Assign / unassign | `fj issue assign -R origin <n> <user>` / `fj issue unassign -R origin <n> <user>` |
| Pull requests | `fj pr view|comment|status|diff|checkout -R origin <n>` |

Read the whole thread (`… comments`) before acting on an issue; decisions live in the comments,
not in the body.

Behaviour that has cost sessions real time:

- `fj issue view <n>` prints only the body. Comments need the `comments` subcommand, which
  prints nothing when the thread is empty.
- `fj issue comment` prints nothing on success. Confirm with `… comments`; do not re-post.
- Always pass the body (`--body`, `--body-file`, or as an argument). An omitted body opens
  `$EDITOR` and hangs a non-interactive run.
- Flags are per-subcommand: `-r/--repo` exists on `issue create`/`search` but not on
  `issue view`, where `-R`/`-H` are the options. When a flag is rejected, fall back to `-R origin`.
- This repo's `origin` is `ssh://git@ssh.git.jedu.ir:222/kuro/Jedu-api.git`. `fj` resolves it
  through the alias in `~/.local/share/forgejo-cli/keys.json`. If a command cannot connect, use
  `-H git.jedu.ir -r kuro/Jedu-api` instead of `-R origin`, and log in with
  `fj auth login -H git.jedu.ir`.

## Fallback: Forgejo REST API

Only when `fj` is unavailable. Authenticate with a repository-restricted token in
`FORGEJO_TOKEN` (`write:issue` scope). Never commit or print the token.

Headers: `Accept: application/json`, `Authorization: token $FORGEJO_TOKEN`,
`Content-Type: application/json`. Base path: `/repos/kuro/Jedu-api`.

- Create: `POST /issues`
- Read: `GET /issues/<number>`
- List: `GET /issues`
- Comments: `GET|POST /issues/<number>/comments`
- Update or close: `PATCH /issues/<number>`
- Labels: `GET /labels`; apply via the issue-label endpoints in the instance OpenAPI document
  at `https://git.jedu.ir/swagger.v1.json`

Use pagination when listing: read the `Link` and `x-total-count` headers rather than assuming
one page holds every result.

## Pull requests as a triage surface

**PRs as a request surface: no.**

## When a skill says "publish to the issue tracker"

Create a Forgejo issue in `kuro/Jedu-api`.

## When a skill says "fetch the relevant ticket"

Fetch the issue and all its comments — `fj issue view -R origin <n> comments`.

## Wayfinding operations

The map is one Forgejo issue with child issues represented by explicit references.

- Map: an issue labelled `wayfinder:map`, containing Notes, Decisions-so-far, and Fog.
- Child ticket: an issue whose body begins with `Part of #<map>`, labelled `wayfinder:<type>` where type is `research`, `prototype`, `grilling`, or `task`.
- Blocking: add `Blocked by: #<number>, #<number>` near the top of the child issue. A ticket is unblocked when every referenced blocker is closed.
- Frontier: list open child issues, then discard issues with an open blocker or assignee. The first issue in map order wins.
- Claim: assign the issue to the acting Forgejo user before starting work — `fj issue assign -R origin <n> kuro`.
- Resolve: comment with the result (`fj issue comment -R origin <n> "…"`), close it (`fj issue close -R origin <n>`), then append a short result and issue link to the map's Decisions-so-far section via `fj issue edit -R origin <map> body "…"`.
