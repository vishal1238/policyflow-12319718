<?php

namespace App\Services;

use App\Http\Controllers\PolicyRequestController;
use App\Models\PolicyRequest;
use Illuminate\Support\Facades\DB;

class IssuanceService
{
    public function submit(string $uuid): PolicyRequest
    {
        $policy = PolicyRequest::where('uuid', $uuid)->firstOrFail();

        $result = DB::transaction(function () use ($policy) {
            $row = PolicyRequest::whereKey($policy->id)->lockForUpdate()->firstOrFail();

            if ($row->state === 'ISSUED') {
                return ['policy' => $row, 'early' => true];
            }

            if ($row->state === 'PENDING_CONFIRMATION') {
                return ['policy' => $row, 'early' => true];
            }

            $row->attempt_count = (int) $row->attempt_count + 1;
            $row->save();

            $row->issuanceAttempts()->create([
                'attempt_no' => $row->attempt_count,
                'outcome' => 'STARTED',
                'response' => ['status' => 'submitted'],
            ]);

            PolicyRequestController::changeState($row, 'SUBMITTED', 'Submission initiated.');

            return ['policy' => $row->fresh(), 'early' => false];
        });

        if ($result['early']) {
            return $result['policy'];
        }

        $policy = $result['policy'];
        $service = app(MockInsurer::class);

        try {
            $response = $service->issue($uuid, [
                'policy_request_uuid' => $policy->uuid,
                'agent_id' => $policy->agent_id,
                'customer_name' => $policy->customer_name,
                'destination' => $policy->destination,
                'plan_code' => $policy->plan_code,
            ], $policy->mock_scenario ?? 'SUCCESS');

            if (($response['status'] ?? null) === 'issued') {
                $policy->provider_reference = $response['provider_reference'];
                $policy->policy_number = $response['policy_number'];
                $policy->last_response = $response;
                $policy->save();
                PolicyRequestController::changeState($policy, 'ISSUED', 'Policy issued by insurer.');

                return $policy->fresh();
            }

            if (($response['status'] ?? null) === 'pending') {
                $policy->last_response = $response;
                $policy->last_error = null;
                $policy->save();
                PolicyRequestController::changeState($policy, 'PENDING_CONFIRMATION', 'Insurer waiting on callback.');

                return $policy->fresh();
            }

            $policy->last_response = $response;
            $policy->last_error = 'Unexpected insurer response';
            $policy->save();
            PolicyRequestController::changeState($policy, 'FAILED', 'Insurer returned an unexpected response.');

            return $policy->fresh();
        } catch (\Throwable $e) {
            $policy->last_response = ['error' => $e->getMessage()];
            $policy->last_error = $e->getMessage();
            $policy->save();

            if (str_contains(strtolower($e->getMessage()), 'timeout')) {
                $lookup = $service->lookup($uuid);
                if ($lookup && ! empty($lookup['provider_reference'])) {
                    $policy->provider_reference = $lookup['provider_reference'];
                    $policy->policy_number = $lookup['policy_number'];
                    $policy->save();
                    PolicyRequestController::changeState($policy, 'ISSUED', 'Policy found via status lookup after timeout.');

                    return $policy->fresh();
                }

                PolicyRequestController::changeState($policy, 'NEEDS_RECONCILIATION', 'Insurer timed out and no policy was found.');

                return $policy->fresh();
            }

            PolicyRequestController::changeState($policy, 'FAILED', 'Temporary insurer failure.');

            return $policy->fresh();
        }
    }
}
