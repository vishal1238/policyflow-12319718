<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_callback_sent_three_times_only_applied_once(): void
    {
        $request = PolicyRequest::create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'client_request_id' => 'REQ-20',
            'agent_id' => 'AGENT-20',
            'customer_name' => 'Callback User',
            'date_of_birth' => '1988-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-07-01',
            'trip_end' => '2026-07-03',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium' => '36.00',
            'state' => 'SUBMITTED',
            'provider_reference' => 'REF-CALLBACK-1',
            'mock_scenario' => 'SUCCESS',
        ]);

        $payload = [
            'provider_event_id' => 'EVT-1',
            'provider_reference' => 'REF-CALLBACK-1',
            'type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-CALLBACK-1',
        ];

        $first = $this->postJson('/api/mock-insurer/callback', $payload);
        $second = $this->postJson('/api/mock-insurer/callback', $payload);
        $third = $this->postJson('/api/mock-insurer/callback', $payload);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $third->assertStatus(200);
        $this->assertSame(1, ProviderEvent::count());
        $request->refresh();
        $this->assertSame('ISSUED', $request->state);
        $this->assertSame('POL-CALLBACK-1', $request->policy_number);
    }

    public function test_callback_after_issued_cannot_change_state_or_policy_number(): void
    {
        $request = PolicyRequest::create([
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'client_request_id' => 'REQ-21',
            'agent_id' => 'AGENT-21',
            'customer_name' => 'Issued User',
            'date_of_birth' => '1987-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-03',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium' => '36.00',
            'state' => 'ISSUED',
            'provider_reference' => 'REF-ALREADY-ISSUED',
            'policy_number' => 'POL-ALREADY-ISSUED',
            'mock_scenario' => 'SUCCESS',
        ]);

        $response = $this->postJson('/api/mock-insurer/callback', [
            'provider_event_id' => 'EVT-2',
            'provider_reference' => 'REF-ALREADY-ISSUED',
            'type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-NEW',
        ]);

        $response->assertStatus(200);
        $request->refresh();
        $this->assertSame('ISSUED', $request->state);
        $this->assertSame('POL-ALREADY-ISSUED', $request->policy_number);
    }
}
