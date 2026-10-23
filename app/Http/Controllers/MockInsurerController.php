<?php

namespace App\Http\Controllers;

use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\MockInsurer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MockInsurerController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'idempotency_key' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
            'agent_id' => ['required', 'string'],
            'provider_reference' => ['nullable', 'string'],
        ]);

        $service = app(MockInsurer::class);
        $result = $service->issue($payload['idempotency_key'], $payload, 'SUCCESS');

        return response()->json(['data' => $result]);
    }

    public function status(string $key): JsonResponse
    {
        $service = app(MockInsurer::class);

        return response()->json(['data' => $service->lookup($key) ?? ['status' => 'not_found']]);
    }

    public function callback(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'provider_event_id' => ['required', 'string'],
            'provider_reference' => ['required', 'string'],
            'type' => ['required', 'in:POLICY_ISSUED,POLICY_FAILED'],
            'policy_number' => ['nullable', 'string'],
        ]);

        $event = ProviderEvent::where('provider_event_id', $payload['provider_event_id'])->first();
        if ($event) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        $policy = PolicyRequest::where('provider_reference', $payload['provider_reference'])->first();
        if (! $policy) {
            return response()->json(['error' => ['code' => 'POLICY_NOT_FOUND', 'message' => 'Policy not found.']], 404);
        }

        DB::transaction(function () use ($policy, $payload) {
            $policy = PolicyRequest::whereKey($policy->getKey())->lockForUpdate()->firstOrFail();

            $event = ProviderEvent::create([
                'policy_request_id' => $policy->id,
                'provider_event_id' => $payload['provider_event_id'],
                'type' => $payload['type'],
                'payload' => $payload,
            ]);

            if ($policy->state === 'ISSUED') {
                return;
            }

            if ($payload['type'] === 'POLICY_ISSUED') {
                if ($policy->policy_number && $policy->policy_number !== $payload['policy_number']) {
                    return;
                }
                $policy->provider_reference = $payload['provider_reference'];
                $policy->policy_number = $payload['policy_number'];
                $policy->state = 'ISSUED';
                $policy->save();
                $policy->stateHistory()->create([
                    'from_state' => $policy->getOriginal('state'),
                    'to_state' => 'ISSUED',
                    'reason' => 'Insurer callback accepted.',
                ]);
            }

            if ($payload['type'] === 'POLICY_FAILED') {
                $policy->last_error = 'Provider reported policy failed.';
                $policy->state = 'FAILED';
                $policy->save();
                $policy->stateHistory()->create([
                    'from_state' => $policy->getOriginal('state'),
                    'to_state' => 'FAILED',
                    'reason' => 'Provider callback reported failure.',
                ]);
            }
        });

        return response()->json(['data' => ['status' => 'accepted']]);
    }
}
