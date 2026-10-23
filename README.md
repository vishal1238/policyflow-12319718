# PolicyFlow

PolicyFlow is a small Laravel 11-style REST API for issuing travel-insurance policies. The app is intentionally simple: no UI, no queues, no Docker, no Redis, and no background jobs. It uses SQLite by default, but the same migrations work with MySQL/PostgreSQL with the usual DB settings in `.env`.

## Stack

- Laravel 12 app (works for the requested Laravel 11 API pattern)
- SQLite by default
- MySQL/Postgres supported via same migrations
- PHPUnit for feature and unit coverage

## Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```
2. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
3. Generate the app key:
   ```bash
   php artisan key:generate
   ```
4. Run the database migrations:
   ```bash
   php artisan migrate
   ```
5. Start the app:
   ```bash
   php artisan serve
   ```

## Test command

```bash
php artisan test
```

## Switching to MySQL/Postgres

Update `.env` to the DB driver you want, for example:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=policyflow
DB_USERNAME=root
DB_PASSWORD=secret
```

or:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=policyflow
DB_USERNAME=postgres
DB_PASSWORD=secret
```

Then run:

```bash
php artisan migrate
```

The schema is intentionally database-agnostic and uses standard SQL features.

## API endpoints

### Create policy request

```bash
POST /api/policy-requests
```

Body example:

```json
{
  "client_request_id": "REQ-1001",
  "agent_id": "AGENT-01",
  "customer_name": "Jane Doe",
  "date_of_birth": "1990-01-15",
  "destination": "DOMESTIC",
  "trip_start": "2026-06-01",
  "trip_end": "2026-06-05",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```

Response:

- `201` for a new request
- `200` for a duplicate `(agent_id, client_request_id)`
- `422` for validation issues

### Fetch a policy request

```bash
GET /api/policy-requests/{uuid}
```

Returns the current state, premium, provider reference, policy number, attempt count, last error, and historical state transitions.

### List policy requests

```bash
GET /api/policy-requests?status=VALIDATED&page=1
```

### Submit for issuance

```bash
POST /api/policy-requests/{uuid}/submit
```

This is the core issuance flow. It takes a row lock, increments attempts, moves the request to `SUBMITTED`, then calls the mock insurer directly in-process.

### Mock insurer callback

```bash
POST /api/mock-insurer/callback
```

Body:

```json
{
  "provider_event_id": "EVT-001",
  "provider_reference": "REF-ABC123",
  "type": "POLICY_ISSUED",
  "policy_number": "POL-9001"
}
```

Success response:

```json
{
  "data": {
    "status": "accepted"
  }
}
```

Error response:

```json
{
  "error": {
    "code": "POLICY_NOT_FOUND",
    "message": "Policy not found."
  }
}
```

## Mock insurer behavior

The provider logic lives in `app/Services/MockInsurer.php` and is called directly from the app, not over HTTP. This avoids deadlocks and makes local testing much simpler than using `php artisan serve` plus HTTP calls to the same app.

Supported scenarios in `mock_scenario` are:

- `SUCCESS`
- `TEMPORARY_FAILURE`
- `TIMEOUT_AFTER_ISSUE`
- `DELAYED_CALLBACK`
- `DUPLICATE_CALLBACK`

## Reliability cases implemented

1. Duplicate intake
   - unique index on `(agent_id, client_request_id)`
   - row lock within the store transaction
   - idempotency check before creating a new record

2. Duplicate callback
   - unique index on `provider_event_id`
   - a second callback returns `{"status":"duplicate_ignored"}` and does nothing

3. Timeout handling
   - if the provider times out, the app performs a status lookup
   - if a matching policy exists, it is accepted as issued
   - if it does not exist, the request moves to `NEEDS_RECONCILIATION`

## Other reliability cases to handle later

The following are not fully automated in this small app, but they are the next areas to cover:

- duplicate policy numbers from insurer retries
- provider event replay after a successful issue
- retriable callback ordering issues
- out-of-order state transitions across multiple worker processes

The usual approach would be to add stronger idempotency keys, store provider event dedupe tables, lock rows consistently, and treat the insurer callback as a source of truth after successful validation.

## Assumptions and limitations

- This is a small internal API, not a production-grade insurer system.
- Premiums are calculated using integer and decimal math with `bcmath` and are stored as strings.
- The mock insurer is in-memory and deliberately simple.
- There is no external provider integration, no real authentication, and no queue system.
- Callback handling is synchronous and local to the app.

## Example request commands

See `examples/requests.sh` for curl examples.

## Migration notes

The migrations create the following tables:

- `policy_requests`
- `issuance_attempts`
- `provider_events`
- `policy_state_history`

The schema is compatible with SQLite, MySQL, and PostgreSQL as long as the driver is configured in `.env`.
