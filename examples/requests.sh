#!/usr/bin/env bash

BASE_URL="http://localhost:8000"

printf '\nCreate a valid request\n'
curl -X POST "$BASE_URL/api/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "REQ-1001",
    "agent_id": "AGENT-01",
    "customer_name": "Jane Doe",
    "date_of_birth": "1990-01-15",
    "destination": "DOMESTIC",
    "trip_start": "2026-06-01",
    "trip_end": "2026-06-05",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'

printf '\nValidation error\n'
curl -X POST "$BASE_URL/api/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "REQ-1002",
    "agent_id": "AGENT-01",
    "customer_name": "Jane Doe",
    "date_of_birth": "1990-01-15",
    "destination": "DOMESTIC",
    "trip_start": "2026-06-10",
    "trip_end": "2026-06-05",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'

printf '\nDuplicate request\n'
curl -X POST "$BASE_URL/api/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "REQ-1001",
    "agent_id": "AGENT-01",
    "customer_name": "Jane Doe",
    "date_of_birth": "1990-01-15",
    "destination": "DOMESTIC",
    "trip_start": "2026-06-01",
    "trip_end": "2026-06-05",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'

printf '\nCallback\n'
curl -X POST "$BASE_URL/api/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -d '{
    "provider_event_id": "EVT-001",
    "provider_reference": "REF-ABC123",
    "type": "POLICY_ISSUED",
    "policy_number": "POL-9001"
  }'
