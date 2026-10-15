# Jedu production deployment

One host, one compose project, three application images from Nexus:

```
                        ┌──────────────────────────────────────────┐
   :80 / :443  ────────▶│ caddy   (TLS + hostname routing)         │
                        └───┬───────────┬───────────┬──────────────┘
                            │           │           │
                    api:8000│    shop:80│   admin:80│
                  ┌─────────▼──┐  ┌─────▼─────┐  ┌──▼────────┐
                  │ api        │  │ shop      │  │ admin     │
                  │ (FrankenPHP)│  │ (Next.js) │  │ (nginx)   │
                  └─┬────────┬─┘  └───────────┘  └───────────┘
                    │        │
        ┌───────────▼┐  ┌────▼─────┐  ┌──────────────┐
        │ worker     │  │ postgres │  │ redis        │
        │ Horizon +  │  │ pgroonga │  │ typesense    │
        │ scheduler  │  └──────────┘  └──────────────┘
        └────────────┘
```

`api` and `worker` are the same image with a different `DEPLOYMENT_TYPE` (the
image's entrypoint branches on it). Only Caddy publishes ports; everything else
is reached over the compose network by service name.

## Quick start

On the production host, as a user that can run `docker`:

```bash
# 1. Get this directory onto the server (any path; /opt/jedu is a good home).
sudo mkdir -p /opt/jedu && sudo chown "$USER" /opt/jedu
git clone ssh://git@ssh.git.jedu.ir:222/kuro/Jedu-api.git /tmp/jedu-api
cp -r /tmp/jedu-api/deploy/production/. /opt/jedu/
cd /opt/jedu

# 2. DNS first: api/shop/admin.<your-domain> must point at this host.

# 3. Install. Prompts for the domains, the ACME email and the three image tags,
#    generates every secret, pulls, starts, waits for health.
./install.sh --domain jedu.ir --email ops@jedu.ir

# 4. Check.
docker compose ps
curl -fsS https://api.jedu.ir/up && echo
```

Re-running `install.sh` is safe and is the supported way to change
configuration: values already in `.env` win, so secrets are never rotated behind
a live database. The installer is non-interactive when stdin is not a terminal;
`-y` forces that and fails on a missing required value.

The admin image is not published to Nexus yet, so the very first install needs
`--skip-admin` (the admin hostname then answers nothing at all). See
[Known gaps](#known-gaps).

## What `install.sh` does

1. **Preflight** — docker + compose v2 + openssl present, daemon reachable,
   warns if port 80/443 is already taken.
2. **Reads `.env.example` as the default template**, then overlays the existing
   `.env` and any flags, then prompts for the rest. Nothing is asked twice.
3. **Generates** `APP_KEY` (`base64:` + 32 random bytes, byte-identical to
   `artisan key:generate --show`), the PostgreSQL password, the Redis password,
   the Typesense API key, the storefront revalidation secret and the Horizon
   password — each only if it is not already set.
4. **Writes `.env`** (mode 600) with the derived values: `APP_URL`,
   `FRONTEND_SHOP_DOMAIN`, the Caddy security/TLS settings, and the admin vhost.
5. **Validates** the compose file with `docker compose config --quiet`.
6. **Logs in** to `nexus.jedu.ir` if you give it credentials, then pulls each
   image individually so one failure names the image that is missing.
7. **Starts** the stack, waits up to 10 minutes for the API's healthcheck
   (`/up`), then sets the stored search engine to Typesense with
   `php artisan pgroonga:engine typesense` (the same call `deploy.php` makes).
8. Prints the URLs and the operational commands.

## Day-to-day

```bash
cd /opt/jedu

docker compose ps                      # what is running and healthy
docker compose logs -f api             # API logs (LOG_CHANNEL=stderr by default)
docker compose logs -f worker          # Horizon + scheduler
docker compose exec api bash           # a shell in the API container
docker compose exec api php artisan about

# Ship a new API build (migrations run automatically on api start):
sed -i 's|^JEDU_API_IMAGE=.*|JEDU_API_IMAGE=nexus.jedu.ir/docker-hub/jedu-api:<git-sha>|' .env
docker compose pull api worker && docker compose up -d api worker

# After changing STOREFRONT_REVALIDATE_SECRET, restart the worker: queue workers
# keep the config they booted with, so the old secret would keep being sent.
docker compose up -d --force-recreate worker

# Re-index the search engine (slow; the installer does not do this by default).
docker compose exec api php artisan scout:import 'App\Models\Product'
docker compose exec api php artisan products:index-availability --sync

# Stop, and stop while deleting every volume (irreversible).
docker compose down
docker compose down -v
```

Horizon is served at `https://<api-domain>/horizon`, behind HTTP basic auth from
`HORIZON_USERNAME` / `HORIZON_PASSWORD`; with either value empty it answers 401
to everyone.

## Good to know

- **`STOREFRONT_URL` is `http://shop:80`, not the public shop domain.** The
  revalidation webhook is a server-to-server call from the API container;
  using the browser-facing host resolves back to the API container and fails
  with `cURL error 7` (see `config/storefront.php` and ADR 0019).
- **`STOREFRONT_REVALIDATE_SECRET` is one value** used for both the API's
  `STOREFRONT_REVALIDATE_SECRET` and the shop's `REVALIDATE_SECRET`; compose
  passes the same variable to both, so they cannot drift. While it is empty the
  webhook is a logged no-op and the storefront only refreshes on TTL.
- **`SANCTUM_STATEFUL_DOMAINS`** is derived from the three hostnames. The admin
  panel authenticates with a cookie (`withCredentials: true`), so all three must
  stay correct or its requests come back 401.
- **`.env` is the single source of truth.** `DB_*`, `REDIS_*`, `TYPESENSE_*`,
  `APP_URL`, `FRONTEND_SHOP_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` and the shop's
  `NEXT_PUBLIC_*` are computed in `docker-compose.yml` from the domain and
  secret keys, so changing a domain is a one-line edit plus a re-run.
- **Storage is a named volume**, not a bind mount: the image's `appuser`
  (uid 1000) owns `/app/storage`, and Docker copies that ownership into a fresh
  named volume. A bind mount would need `chown -R 1000:1000` on the host first.
- **`APP_NAME` drives `HORIZON_PREFIX`**, which is the Redis key prefix for every
  queue. Keep it stable across a migration from an existing deployment, or set
  `HORIZON_PREFIX` explicitly in `.env`.

## Go-live checklist

- [ ] **DNS** — `api`, `shop` and `admin` resolve to this host *before* the
      first start; Caddy retries certificates, but Let's Encrypt rate-limits
      repeated failures.
- [ ] **Firewall** — 80 and 443 open, 5432/6379/8108/8000 not exposed. Only
      Caddy publishes ports, so this is a host-firewall check.
- [ ] **Mail** — `MAIL_MAILER` defaults to `log`, which delivers nothing. Set
      `MAIL_MAILER=smtp` and the `MAIL_*` credentials, then
      `docker compose up -d --force-recreate api worker`. OTP login and order
      notifications depend on it.
- [ ] **Real client IPs (recommended, see below)** — without it, per-IP rate
      limits apply to the Caddy container, not to the visitor.
- [ ] **Backups** — `jedu-postgres-data` is the whole system of record. Also
      back up `jedu-storage-app` (uploads and export artifacts) and `.env`.
      ```
      docker compose exec -T postgres pg_dump -U jedu jedushop | gzip > /backup/jedushop-$(date +%F).sql.gz
      ```
- [ ] **Typesense models** — the first index build downloads the
      `multilingual-e5-small` embedding model. If this host cannot reach the
      model host, pre-seed `jedu-typesense-data` from a machine that can.
- [ ] **Verify** — `curl -fsS https://<api-domain>/up`, load the storefront,
      log into the admin panel, then check
      `docker compose exec api php artisan pgroonga:engine status`.

### Known gaps

These are not installer bugs; each is a decision that lives in another
repository.

1. **Admin panel image.** `nexus.jedu.ir/docker-hub/jedu-admin` does not exist
   yet, so install with `--skip-admin` and add it later. The `admin` service
   already passes `VITE_API_URL` to the container, so nothing here needs to
   change once the image is published. Two things about the current
   `JeduFront/admin-panel` Dockerfile are worth fixing in the same pass:
   - `VITE_API_URL` is read through `import.meta.env` at **build** time with no
     runtime fallback, so the image carries whichever API URL was present when
     it was built.
   - it has no `.dockerignore`, so `COPY . .` copies the developer's `.env`
     (currently `http://jedu.test/api/v1/admin`) into the image.

   Fix both by moving the lookup to run time, which is how the shop solved the
   same problem: exclude `.env*` with a `.dockerignore`, and have the image's
   nginx entrypoint write `window.__ENV__` from the container environment on
   every start, via a hook in `/docker-entrypoint.d/`. Do **not** reach for
   `ARG VITE_API_URL`: a build arg still forces a separate build per environment,
   which is the problem being fixed. The `VITE_API_URL` on the `admin` service in
   `docker-compose.yml` is what the entrypoint reads.
2. **Real client IPs.** Laravel's `TrustProxies` middleware is in the default
   global stack but trusts nothing until it is configured, and this repo has no
   `config/trustedproxy.php`. Behind Caddy every request therefore appears to
   come from the Caddy container, which collapses the per-IP buckets used by
   `throttle:5,1` / `throttle:10,1` and by `throttle.password-login:*`
   (see `config/password_throttle.php`) into one shared bucket. The fix is two
   lines in the API repo plus a rebuild:
   ```php
   // config/trustedproxy.php
   return ['proxies' => env('TRUSTED_PROXIES')];
   ```
   and `TRUSTED_PROXIES=*` in `.env` here. `*` means "trust the immediate
   peer", which is safe only because the `api` container publishes no ports —
   keep it that way, or list Caddy's network instead.
3. **Typesense through Nexus.** `nexus.jedu.ir/docker-hub-proxy/typesense/typesense:29.0`
   answers `502`, so `.env.example` pulls Typesense from Docker Hub. Push a copy
   to `nexus.jedu.ir/docker-hub/typesense` and point `TYPESENSE_IMAGE` at it if
   the production host has no Docker Hub access.

## Troubleshooting

| Symptom | Where to look |
| --- | --- |
| `install.sh` stops on "cannot pull" | `docker pull <image>` by hand; a private repo needs `--registry-user`/`--registry-password`, and the admin image needs `--skip-admin` until it is published |
| `jedu-api` never becomes healthy | `docker compose logs api` — the entrypoint waits for PostgreSQL/Redis, then runs migrations; a wrong `POSTGRES_PASSWORD` shows up here |
| `admin.<domain>` returns 502 | the admin service is missing (installed with `--skip-admin`) or still starting |
| Certificate errors in `docker compose logs caddy` | the hostname does not resolve to this host yet, or port 80 is closed for the ACME HTTP challenge |
| Storefront shows stale content after an edit | `docker compose logs worker` for the revalidation job, and confirm the API's `STOREFRONT_URL`/secret match the shop's `REVALIDATE_SECRET` |
| Storefront 500s | `docker compose logs shop`; verify `NEXT_PUBLIC_API_URL` resolved with `docker compose config` |
| Search returns nothing | the engine setting and the index are per-database: `docker compose exec api php artisan pgroonga:engine status`, then `--rebuild-search` |

## Verifying this bundle without a production host

```bash
bash -n install.sh                       # syntax
docker compose config --quiet            # YAML + every ${...} resolves
docker compose config | less             # the resolved configuration
docker compose run --rm --no-deps caddy \
  caddy validate --config /etc/caddy/Caddyfile
```

Everything in this directory was checked this way (Caddy 2.11.7, Compose
v2.40.2), plus:

- `install.sh` was driven through five scenarios against a stub `docker`: a
  fresh `--no-tls --skip-admin` install, an identical re-run (secrets
  unchanged), a TLS re-run without `--email` (fails loudly instead of writing a
  Caddyfile with an empty `email` directive), a TLS re-run with `--api-tag`, and
  `--reset-env`.
- `caddy validate` accepts the Caddyfile in TLS and `--no-tls` modes, and with
  the `--skip-admin` placeholder site.
- `postgres`, `redis` and `typesense` were started from this exact compose file
  and all three reached `healthy`. Redis answered `PONG` with the generated
  password and `NOAUTH` without it, Typesense answered `{"ok":true}` on :8108,
  and the pgroonga image offers the `pgroonga` extension (4.1.0) that
  `0000_00_000000_setup_pgroonga_extension.php` creates.

What was *not* run here is a full end-to-end start: it publishes 80/443, which
this development machine already uses, and it needs the admin image, which is
not published yet.

## Relationship to the other compose files

`docker-compose-production.yaml` at the repository root is the older, narrower
file: it runs only `api` + `worker` from `nexus.jedu.ir/repository/docker-hub/…`
and expects PostgreSQL, Redis and Typesense to exist elsewhere. This directory
supersedes it for a single-host install — it runs all three applications and the
datastores, and uses the `nexus.jedu.ir/docker-hub/…` path that
`deploy/production/.env.example` and the shop's image tag agree on. Keep the root
file only if something still deploys with it.
