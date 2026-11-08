# Payment Ledger

Laravel 12 API, TypeScript/Fastify validator, and PostgreSQL assignment.

Work is tracked in the [Payment Ledger project](https://github.com/users/albrtblzs/projects/4).

## Development workflow

- Keep `master` as the integration branch.
- Before starting an issue, create `feature/<issue-number>-<short-name>` and move the issue to In Progress.
- Implement and verify only that issue's scope.
- Open a pull request targeting `master` with `Closes #<issue-number>` and validation evidence.
- Keep issues In Progress until their pull requests are merged. Do not merge without review.

See the repository issues for the implementation plan and acceptance criteria.

## Run locally

Prerequisites: Git and Docker Desktop with Linux containers (Docker Compose v2).

```sh
git clone https://github.com/albrtblzs/bbox.git
cd bbox
docker compose up --build -d --wait
```

The API is available at http://localhost:8000; its health endpoint is `/up`.
The validator's `/health` endpoint and PostgreSQL are available only inside the Compose network.
Both services are currently skeletons; ledger endpoints and validation rules are tracked in subsequent issues.

Startup generates a container-local Laravel key and runs migrations after PostgreSQL is healthy.
PostgreSQL data persists in a named volume. `docker compose down` stops the services without deleting data.
The PHP development server and example database credentials are intended for this local assignment.

Optionally copy `.env.example` to `.env` to change the exposed API port or validator limit settings.
The limits will become active when the validation rules are implemented in issue #4.

## Checks

```sh
docker compose exec backend php artisan test
docker compose exec validator npm test
docker compose exec validator npm run typecheck
docker compose exec validator npm run build
```

Dependency lockfiles are committed. Dependencies are installed inside images; do not commit `vendor`, `node_modules`, `.env`, logs, or generated build files.
