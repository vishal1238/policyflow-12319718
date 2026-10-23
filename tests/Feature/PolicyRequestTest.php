<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_returns_201_and_premium(): void
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'REQ-1',
            'agent_id' => 'AGENT-1',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-02',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.premium', '50.00');
        $response->assertJsonPath('data.state', 'VALIDATED');
        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_trip_end_before_trip_start_returns_422(): void
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'REQ-2',
            'agent_id' => 'AGENT-2',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1985-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-10',
            'trip_end' => '2026-06-09',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $response->assertStatus(422);
    }

    public function test_unsupported_plan_returns_422(): void
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'REQ-3',
            'agent_id' => 'AGENT-3',
            'customer_name' => 'Sam Smith',
            'date_of_birth' => '1980-02-02',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-03',
            'plan_code' => 'GOLD',
            'sum_insured' => 100000,
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_request_returns_existing_and_one_row(): void
    {
        $payload = [
            'client_request_id' => 'REQ-4',
            'agent_id' => 'AGENT-4',
            'customer_name' => 'Alice Brown',
            'date_of_birth' => '1992-05-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-08-01',
            'trip_end' => '2026-08-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $first = $this->postJson('/api/policy-requests', $payload);
        $second = $this->postJson('/api/policy-requests', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200);
        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_get_request_returns_state_and_history(): void
    {
        $request = PolicyRequest::create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'client_request_id' => 'REQ-5',
            'agent_id' => 'AGENT-5',
            'customer_name' => 'Test User',
            'date_of_birth' => '1990-01-01',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-01-01',
            'trip_end' => '2026-01-02',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium' => '21.60',
            'state' => 'VALIDATED',
            'mock_scenario' => 'SUCCESS',
        ]);

        $request->stateHistory()->create([
            'from_state' => 'RECEIVED',
            'to_state' => 'VALIDATED',
            'reason' => 'Initial validation complete.',
        ]);

        $response = $this->getJson('/api/policy-requests/' . $request->uuid);
        $response->assertStatus(200);
        $response->assertJsonPath('data.state', 'VALIDATED');
        $response->assertJsonPath('data.premium', '21.60');
    }
}
