<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_submission_issues_policy_once(): void
    {
        $request = PolicyRequest::create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'client_request_id' => 'REQ-10',
            'agent_id' => 'AGENT-10',
            'customer_name' => 'Lucky User',
            'date_of_birth' => '1989-03-04',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-09-01',
            'trip_end' => '2026-09-03',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium' => '36.00',
            'state' => 'VALIDATED',
            'mock_scenario' => 'SUCCESS',
        ]);

        $first = $this->postJson('/api/policy-requests/' . $request->uuid . '/submit');
        $second = $this->postJson('/api/policy-requests/' . $request->uuid . '/submit');

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertDatabaseCount('policy_requests', 1);
        $request->refresh();
        $this->assertSame('ISSUED', $request->state);
        $this->assertNotNull($request->policy_number);
    }

    public function test_issued_to_submitted_rejected(): void
    {
        $request = PolicyRequest::create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'client_request_id' => 'REQ-11',
            'agent_id' => 'AGENT-11',
            'customer_name' => 'Nope',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-10-01',
            'trip_end' => '2026-10-03',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium' => '36.00',
            'state' => 'ISSUED',
            'policy_number' => 'POL-TEST-1',
            'provider_reference' => 'REF-TEST-1',
            'mock_scenario' => 'SUCCESS',
        ]);

        $response = $this->postJson('/api/policy-requests/' . $request->uuid . '/submit');
        $response->assertStatus(200);
        $request->refresh();
        $this->assertSame('ISSUED', $request->state);
    }
}
