# PolicyFlow

PolicyFlow is a lightweight Laravel REST API for issuing travel-insurance policies.

It is intentionally simple: no UI, no Docker, no Redis, no queues, and no background workers. SQLite is the default database, and the same migrations are compatible with MySQL and PostgreSQL.

## Setup

1. Install dependencies:
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

## Run tests

```bash
php artisan test
```

## Database switching

Use SQLite by default, or switch to MySQL/Postgres in `.env`:

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

## API endpoints

### Create a policy request

```bash
POST /api/policy-requests
```

Example body:

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
- `200` if the same `agent_id` + `client_request_id` already exists
- `422` for validation errors

### Get one policy request

```bash
GET /api/policy-requests/{uuid}
```

Returns the state, premium, provider reference, policy number, attempt count, last error, and state history.

### List policy requests

```bash
GET /api/policy-requests?status=VALIDATED&page=1
```

### Submit for issuance

```bash
POST /api/policy-requests/{uuid}/submit
```

### Callback endpoint

```bash
POST /api/mock-insurer/callback
```

Body example:

```json
{
  "provider_event_id": "EVT-001",
  "provider_reference": "REF-ABC123",
  "type": "POLICY_ISSUED",
  "policy_number": "POL-9001"
}
```

## Reliability cases covered

1. Duplicate intake
   - `(agent_id, client_request_id)` is unique
   - the request flow uses row locking and duplicate handling

2. Duplicate callback
   - `provider_event_id` is unique
   - repeated callback payloads are ignored

3. Timeout / reconciliation
   - the app checks insurer status after a timeout
   - missing policy moves to `NEEDS_RECONCILIATION`

## Premium calculation

The premium is calculated using `bcmath` with this formula:

```text
premium = daily_rate x days x age_mult x dest_mult x coverage_mult
```

- Daily rate: BASIC 12, PLUS 20, PREMIUM 30
- Days = trip_end - trip_start + 1, minimum 1
- Age multiplier uses trip-start age:
  - 0-17 = 1.10
  - 18-60 = 1.00
  - 61-70 = 1.50
  - 71+ = 2.00
- Destination multiplier:
  - DOMESTIC = 1.00
  - INTERNATIONAL = 1.80
- Coverage multiplier:
  - 100000 = 1.00
  - 300000 = 1.25
  - 500000 = 1.50

Example:
- INTERNATIONAL, PLUS, 300000, 5 days, age 28
- result: 225.00

## Notes

- The app calls the mock insurer directly from the Laravel process instead of via HTTP to avoid deadlocks in local development.
- This is a small internal API and not a full production insurer integration.
- The README is intentionally focused on setup and behavior rather than generated Laravel template content.
