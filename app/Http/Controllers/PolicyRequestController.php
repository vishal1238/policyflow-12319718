<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePolicyRequestRequest;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\PremiumCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PolicyRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $status = request('status');
        $page = max(1, (int) request('page', 1));

        $query = PolicyRequest::query();

        if ($status) {
            $query->where('state', $status);
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(15, ['*'], 'page', $page);

        return response()->json([
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $request = PolicyRequest::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'data' => [
                'uuid' => $request->uuid,
                'state' => $request->state,
                'premium' => $request->premium,
                'provider_reference' => $request->provider_reference,
                'policy_number' => $request->policy_number,
                'attempt_count' => $request->attempt_count,
                'last_error' => $request->last_error,
                'state_history' => $request->stateHistory()->orderBy('created_at')->get()->map(fn ($entry) => [
                    'from_state' => $entry->from_state,
                    'to_state' => $entry->to_state,
                    'reason' => $entry->reason,
                    'created_at' => $entry->created_at,
                ])->all(),
            ],
        ]);
    }

    public function store(StorePolicyRequestRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $exists = PolicyRequest::where('agent_id', $payload['agent_id'])
            ->where('client_request_id', $payload['client_request_id'])
            ->first();

        if ($exists) {
            return response()->json(['data' => $exists->toArray()], 200);
        }

        try {
            $policyRequest = DB::transaction(function () use ($payload) {
                $existing = PolicyRequest::where('agent_id', $payload['agent_id'])
                    ->where('client_request_id', $payload['client_request_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $premium = PremiumCalculator::calculate(
                    $payload['trip_start'],
                    $payload['trip_end'],
                    $payload['date_of_birth'],
                    $payload['destination'],
                    $payload['plan_code'],
                    (int) $payload['sum_insured'],
                );

                $policyRequest = PolicyRequest::create([
                    'uuid' => (string) Str::uuid(),
                    'client_request_id' => $payload['client_request_id'],
                    'agent_id' => $payload['agent_id'],
                    'customer_name' => $payload['customer_name'],
                    'date_of_birth' => $payload['date_of_birth'],
                    'destination' => $payload['destination'],
                    'trip_start' => $payload['trip_start'],
                    'trip_end' => $payload['trip_end'],
                    'plan_code' => $payload['plan_code'],
                    'sum_insured' => (int) $payload['sum_insured'],
                    'premium' => $premium,
                    'state' => 'RECEIVED',
                    'mock_scenario' => 'SUCCESS',
                    'last_response' => null,
                    'last_error' => null,
                ]);

                $this->changeState($policyRequest, 'VALIDATED', 'Initial validation complete.');

                return $policyRequest;
            });

            return response()->json(['data' => $policyRequest->toArray()], 201);
        } catch (\Throwable $e) {
            if ($e instanceof \Illuminate\Database\QueryException && str_contains($e->getMessage(), 'UNIQUE constraint')) {
                $duplicate = PolicyRequest::where('agent_id', $payload['agent_id'])
                    ->where('client_request_id', $payload['client_request_id'])
                    ->first();

                if ($duplicate) {
                    return response()->json(['data' => $duplicate->toArray()], 200);
                }
            }

            throw $e;
        }
    }

    public function submit(string $uuid): JsonResponse
    {
        $policyRequest = PolicyRequest::where('uuid', $uuid)->lockForUpdate()->firstOrFail();

        if ($policyRequest->state === 'ISSUED') {
            return response()->json(['data' => $policyRequest->toArray()]);
        }

        if ($policyRequest->state === 'PENDING_CONFIRMATION') {
            return response()->json(['data' => $policyRequest->toArray()]);
        }

        $this->changeState($policyRequest, 'SUBMITTED', 'Submission initiated.');

        $policyRequest->increment('attempt_count');

        $attemptNo = $policyRequest->attempt_count;
        $policyRequest->issuanceAttempts()->create([
            'attempt_no' => $attemptNo,
            'outcome' => 'STARTED',
            'response' => ['status' => 'submitted'],
        ]);

        $scenario = $policyRequest->mock_scenario ?: 'SUCCESS';

        try {
            $service = app(\App\Services\MockInsurer::class);
            $result = $service->issue($uuid, [
                'policy_request_uuid' => $uuid,
                'agent_id' => $policyRequest->agent_id,
                'customer_name' => $policyRequest->customer_name,
            ], $scenario);

            $policyRequest->last_response = $result;

            if (($result['status'] ?? null) === 'issued') {
                $policyRequest->provider_reference = $result['provider_reference'];
                $policyRequest->policy_number = $result['policy_number'];
                $this->changeState($policyRequest, 'ISSUED', 'Policy issued by insurer.');
            } elseif (($result['status'] ?? null) === 'pending') {
                $this->changeState($policyRequest, 'PENDING_CONFIRMATION', 'Insurer is awaiting confirmation.');
            } else {
                $policyRequest->last_error = 'Unexpected insurer response';
                $this->changeState($policyRequest, 'FAILED', 'Insurer reported a failure.');
            }

            $policyRequest->save();

            return response()->json(['data' => $policyRequest->fresh()->toArray()]);
        } catch (\Throwable $e) {
            $policyRequest->last_error = $e->getMessage();
            $policyRequest->last_response = ['error' => $e->getMessage()];

            if (str_contains(strtolower($e->getMessage()), 'timeout')) {
                $status = $this->lookupInsurerStatus($uuid);
                if ($status && ! empty($status['provider_reference'])) {
                    $policyRequest->provider_reference = $status['provider_reference'];
                    $policyRequest->policy_number = $status['policy_number'];
                    $this->changeState($policyRequest, 'ISSUED', 'Policy found after timeout lookup.');
                } else {
                    $this->changeState($policyRequest, 'NEEDS_RECONCILIATION', 'Insurer timed out and no policy found.');
                }
            } else {
                $this->changeState($policyRequest, 'FAILED', 'Temporary insurer failure.');
            }

            $policyRequest->save();

            return response()->json(['data' => $policyRequest->fresh()->toArray()]);
        }
    }

    protected function lookupInsurerStatus(string $uuid): ?array
    {
        $service = app(\App\Services\MockInsurer::class);

        return $service->lookup($uuid);
    }

    public static function changeState(PolicyRequest $request, string $newState, string $reason): void
    {
        $allowed = [
            'RECEIVED' => ['VALIDATED'],
            'VALIDATED' => ['SUBMITTED'],
            'SUBMITTED' => ['ISSUED', 'PENDING_CONFIRMATION', 'FAILED', 'NEEDS_RECONCILIATION'],
            'PENDING_CONFIRMATION' => ['ISSUED', 'FAILED'],
            'NEEDS_RECONCILIATION' => ['ISSUED', 'FAILED'],
            'FAILED' => ['SUBMITTED'],
        ];

        $current = $request->state;
        if ($current === 'ISSUED') {
            throw new InvalidArgumentException('ISSUED policy requests cannot change state.');
        }

        if (! in_array($newState, $allowed[$current] ?? [], true)) {
            throw new InvalidArgumentException("Invalid state transition from {$current} to {$newState}.");
        }

        $request->state = $newState;
        $request->save();

        $request->stateHistory()->create([
            'from_state' => $current,
            'to_state' => $newState,
            'reason' => $reason,
        ]);
    }
}
