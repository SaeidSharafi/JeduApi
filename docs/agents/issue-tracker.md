# Issue tracker: Forgejo

Issues and specs for this repo live in Forgejo:

- Repository: `https://git.jedu.ir/kuro/Jedu-api`
- API base: `https://git.jedu.ir/api/v1`
- GitHub is a mirror only; do not manage issues there.

Use Forgejo's REST API for issue operations. Authenticate using a repository-restricted access token supplied through the `FORGEJO_TOKEN` environment variable. The token should have `write:issue` permission for this repository. Never commit or print the token.

## Conventions

Send these headers with authenticated requests:

- `Accept: application/json`
- `Authorization: token $FORGEJO_TOKEN`
- `Content-Type: application/json`

Use the repository API path:

`/repos/kuro/Jedu-api`

Operations:

- Create an issue: `POST /repos/kuro/Jedu-api/issues`
- Read an issue: `GET /repos/kuro/Jedu-api/issues/<number>`
- List issues: `GET /repos/kuro/Jedu-api/issues`
- Read comments: `GET /repos/kuro/Jedu-api/issues/<number>/comments`
- Comment: `POST /repos/kuro/Jedu-api/issues/<number>/comments`
- Update or close: `PATCH /repos/kuro/Jedu-api/issues/<number>`
- List repository labels: `GET /repos/kuro/Jedu-api/labels`
- Apply labels: resolve label IDs, then use the issue-label API endpoints described by the instance's OpenAPI document at `https://git.jedu.ir/swagger.v1.json`.

Use pagination when listing issues. Read the response `Link` and `x-total-count` headers rather than assuming one page contains every result.

## Pull requests as a triage surface

**PRs as a request surface: no.**

## When a skill says "publish to the issue tracker"

Create a Forgejo issue in `kuro/Jedu-api`.

## When a skill says "fetch the relevant ticket"

Fetch the issue and all its comments from the Forgejo API.

## Wayfinding operations

The map is one Forgejo issue with child issues represented by explicit references.

- Map: an issue labelled `wayfinder:map`, containing Notes, Decisions-so-far, and Fog.
- Child ticket: an issue whose body begins with `Part of #<map>`, labelled `wayfinder:<type>` where type is `research`, `prototype`, `grilling`, or `task`.
- Blocking: add `Blocked by: #<number>, #<number>` near the top of the child issue. A ticket is unblocked when every referenced blocker is closed.
- Frontier: list open child issues, then discard issues with an open blocker or assignee. The first issue in map order wins.
- Claim: assign the issue to the acting Forgejo user before beginning work.
- Resolve: comment with the result, close the child issue, then append a short result and issue link to the map's Decisions-so-far section.
