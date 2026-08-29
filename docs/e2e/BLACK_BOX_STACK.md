# Black-box E2E stack

Contract version: `1`

This document is the source-independent operating contract for the Jedu
black-box E2E stack. A runner may use the service names and control APIs below
without importing application code.

`docker-compose.e2e.yml` runs the same application image used for deployment with
real queue workers and isolated dependencies. Set a distinct
`COMPOSE_PROJECT_NAME` for every concurrent run; Compose then gives each run its
own network, named volumes, containers, and service DNS names.

The application and worker only join the internal E2E network. PostgreSQL,
Redis, Mailpit, and the gateway simulator are reachable by service name, while
the network has no external route for provider egress. Provisioning uses the
application's E2E simulated provider clients and Scout uses PostgreSQL's
database driver; PGroonga remains available for the database itself.

## Start

Provide the required secrets and image references, then start the stack:

```sh
export COMPOSE_PROJECT_NAME=jedu-e2e-${RUN_ID:-local}
export APP_KEY='base64:...'
export E2E_APP_URL='http://localhost:8000'
export E2E_CONTROL_KEY='...'
export PAYMENT_SIMULATOR_SECRET='...'
export PAYMENT_SIMULATOR_IMAGE='registry.example/gateway-simulator:tag'
export FRONTEND_PAYMENT_SUCCESS_URL='http://localhost:3000/payment/success'
export FRONTEND_PAYMENT_FAILURE_URL='http://localhost:3000/payment/fail'
docker compose -f docker-compose.e2e.yml up -d
```

Required variables are `APP_KEY`, `E2E_CONTROL_KEY`,
`PAYMENT_SIMULATOR_SECRET`, and `PAYMENT_SIMULATOR_IMAGE`.
`E2E_APP_URL` is required whenever the API is not browser-reachable at
`http://localhost:8000`; it is used by Jedu to generate the simulator callback
URL. The frontend team must provide `FRONTEND_PAYMENT_SUCCESS_URL` and
`FRONTEND_PAYMENT_FAILURE_URL` as absolute, browser-reachable URLs for the
frontend payment result pages. The Compose defaults assume a frontend on
`http://localhost:3000`.

Optional image overrides are `API_IMAGE`, `POSTGRES_IMAGE`, `REDIS_IMAGE`, and
`MAILPIT_IMAGE`. Optional database/runtime overrides are `APP_NAME`,
`APP_DEBUG`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_PREFIX`,
`E2E_REDIS_DB`, `E2E_REDIS_CACHE_DB`, `E2E_REDIS_LOCK_DB`, and `E2E_QUEUE`.
Set `E2E_APP_PORT`, `E2E_MAILPIT_PORT`, and
`E2E_PAYMENT_SIMULATOR_PORT` when another Compose project is already using the
default host ports. If `E2E_APP_PORT` changes, update `E2E_APP_URL` to the same
browser-reachable API URL.

The stack contains these services:

| Service | Role | Health check |
| --- | --- | --- |
| `api` | FrankenPHP application image and migrations | `GET /up` |
| `worker` | Horizon and scheduler using the same image | Supervisor PID |
| `postgres` | PostgreSQL with PGroonga installed | `pg_isready` |
| `redis` | Queue, cache, lock, and rate-limit databases | `redis-cli ping` |
| `mailpit` | SMTP capture and message control API | `/api/v1/info` |
| `payment-simulator` | Browser-facing signed payment gateway | `/health` |

The API is available at `http://localhost:${E2E_APP_PORT:-8000}`. The Mailpit
dashboard is available at `http://localhost:${E2E_MAILPIT_PORT:-8025}` and the
simulator is available at `http://localhost:${E2E_PAYMENT_SIMULATOR_PORT:-8080}`.

Compose waits for dependency health before starting the API, and waits for the
API before starting the worker. The API migration and bootstrap path is run by
the image entrypoint; the worker runs Horizon and the scheduler.

The application and worker use `APP_ENV=e2e`, `QUEUE_CONNECTION=redis`,
`E2E_QUEUE=e2e`, `CACHE_STORE=redis`, and `SCOUT_DRIVER=database`. Redis uses
separate databases for ordinary data, cache, and reset locks. Each Compose
project has separate named PostgreSQL/Redis volumes and service DNS names.

## Scenario reset and teardown

Before each serialized scenario, call the E2E reset endpoint with the per-run
control key, then reset Mailpit and the simulator through their own control
APIs. The reset response supplies the bootstrap credentials and tokens needed
to arrange scenario data through ordinary application APIs.

```sh
curl --fail-with-body \
  -X POST "http://localhost:${E2E_APP_PORT:-8000}/api/v1/e2e/reset" \
  -H "X-E2E-Key: ${E2E_CONTROL_KEY}"
```

The successful response is an `apiResponse()` envelope whose `data` contains:

- `reset_id`: a unique reset identifier for logs and scenario correlation.
- `readiness`: `ready` only after workers have resumed and reported readiness.
- `staff`: `id`, `email`, `phone`, `password`, and a fresh Sanctum `token`.
- `customer`: `id`, `email`, `phone`, `password`, and a fresh Sanctum `token`.

The caller creates products, carts, orders, and other prerequisites through
ordinary APIs using those identities. It does not write directly to the Jedu
database. Browser behavior is caller-owned: payment redirects, simulator
Success/Failure actions, and the return through the normal callback are
performed by the browser boundary, not by a database arrangement shortcut.

Reset failures return HTTP 503 with `metadata.error_code=E2E_RESET_FAILED` and
the same `metadata.reset_id` used in server logs. An overlapping reset returns
HTTP 409. Missing or invalid `X-E2E-Key`, or any request outside `APP_ENV=e2e`,
returns HTTP 403 without touching application state.

Mailpit is reset with `DELETE /api/v1/messages`. The gateway simulator is reset
with `POST /api/v1/reset` and its simulator control secret. The simulator's
browser page exposes explicit Success and Failure actions for each initiated
attempt; the E2E caller must select those actions rather than synthesize a
callback.

## Payment gateway contract

The shop advertises `simulator` only in E2E. Initiation sends a signed JSON
request to the standalone `payment-simulator` service at
`http://payment-simulator/api/v1/attempts`. This is not a Jedu API route. The
`callback_url` inside the request points back to Jedu's normal payment callback
route, which is the route the browser returns through after selecting an
outcome. The request contains:

```json
{
  "order_reference": "order increment id",
  "payment_reference": "payment transaction reference",
  "amount": 1000,
  "callback_url": "http://localhost:8000/api/v1/shop/payment/gateway/callback/payment-uuid",
  "delay_seconds": 0
}
```

`delay_seconds` is optional and must be between 0 and 15. The signature is
HMAC-SHA256 over the canonical JSON payload in `X-Simulator-Signature`. The
simulator returns a `redirect_url`; the browser follows it and chooses Success
or Failure. The callback contains the same Order/Payment references and exact
amount, an `outcome` of `success` or `failure`, and a matching signature.

Jedu validates the signature, references, amount, transaction ownership, and
outcome before changing state. Failure makes the Payment terminally failed but
leaves the Order retryable. A retry creates a new Payment and transaction for
the same Order. Repeated initiation, browser actions, and callbacks are
idempotent.

After callback verification, Jedu returns an HTTP 302 redirect to
`FRONTEND_PAYMENT_SUCCESS_URL` or `FRONTEND_PAYMENT_FAILURE_URL`. The redirect
includes `payment`, `purpose`, and (for Order payments) `order`; failed gateway
verification may also include `error`.

## Testing boundary

The ordinary Pest suite proves application units and integration seams using
fakes; it is not the runner for the assembled multi-container smoke scenario.
That scenario should be executed by a shell/CI harness that starts the Compose
project, waits for health checks, calls the Jedu/Mailpit/simulator control APIs,
and uses the frontend browser harness for redirects and Success/Failure
actions. Pest may remain useful for application-level contract tests, but it
does not replace the external stack and browser boundary.

Always tear down the project after the scenario:

```sh
docker compose -f docker-compose.e2e.yml down -v --remove-orphans
```

Do not reuse a project name or its volumes across concurrent jobs. The
standalone gateway simulator's exact reset and browser-action endpoints are
owned by its repository and should be consumed by the E2E harness at that
boundary. The path and payload above are the Jedu-side contract expected by the
simulator image used with this stack.

## Verification map

The repository-side safety boundary is covered by these automated checks:

- `AppServiceProviderTest`: production rejects E2E-only configuration and E2E
  provider bindings resolve to simulated clients.
- `TestingDatabaseResetControllerTest` and `E2eResetGuardTest`: invalid
  authorization, reset locking, worker gating, and stable correlated failures;
  `ResetE2eEnvironmentAction` performs the isolated cleanup and bootstrap.
- `GatewayServiceTest`: the simulator payment method is absent outside E2E and
  advertised only when enabled in E2E.
- `SimulatorPaymentProcessorTest`: exact signed initiation, callback validation,
  failure-to-success retry on the same Order, terminal transitions, and
  repeated callback idempotency.

The simulated provisioning clients are in-process and deterministic for IMS,
Moodle/Moodle Quiz, SpotPlayer, BBB, and Skyroom. They do not issue provider
HTTP requests. The Compose network is `internal`, so only declared service
traffic is available; the external browser-facing ports are the API, Mailpit,
and simulator ports listed above.
