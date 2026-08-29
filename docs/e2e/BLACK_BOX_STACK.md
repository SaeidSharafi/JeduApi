# Black-box E2E stack

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
export E2E_CONTROL_KEY='...'
export PAYMENT_SIMULATOR_SECRET='...'
export PAYMENT_SIMULATOR_IMAGE='registry.example/gateway-simulator:tag'
docker compose -f docker-compose.e2e.yml up -d
```

The API is available at `http://localhost:${E2E_APP_PORT:-8000}`. The Mailpit
dashboard is available at `http://localhost:${E2E_MAILPIT_PORT:-8025}` and the
simulator is available at `http://localhost:${E2E_PAYMENT_SIMULATOR_PORT:-8080}`.

Compose waits for dependency health before starting the API, and waits for the
API before starting the worker. The API migration and bootstrap path is run by
the image entrypoint; the worker runs Horizon and the scheduler.

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

Always tear down the project after the scenario:

```sh
docker compose -f docker-compose.e2e.yml down -v --remove-orphans
```

Do not reuse a project name or its volumes across concurrent jobs. The
standalone gateway simulator's exact reset and browser-action endpoints are
owned by its repository and should be consumed by the E2E harness at that
boundary.
