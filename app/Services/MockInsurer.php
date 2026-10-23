<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class MockInsurer
{
    public function issue(string $idempotencyKey, array $payload, string $scenario): array
    {
        $cacheKey = 'mock_insurer_policies.' . $idempotencyKey;
        $existing = Cache::get($cacheKey);

        if (is_array($existing) && array_key_exists('provider_reference', $existing)) {
            return $existing;
        }

        $result = $this->buildResult($idempotencyKey, $payload, $scenario);

        if ($result['status'] === 'pending') {
            Cache::put($cacheKey, $result, 3600);

            return $result;
        }

        Cache::put($cacheKey, $result, 3600);

        return $result;
    }

    public function lookup(string $idempotencyKey): ?array
    {
        return Cache::get('mock_insurer_policies.' . $idempotencyKey);
    }

    public function callback(string $eventId, string $providerReference, string $type, ?string $policyNumber = null): array
    {
        return [
            'provider_event_id' => $eventId,
            'provider_reference' => $providerReference,
            'type' => $type,
            'policy_number' => $policyNumber,
            'status' => 'accepted',
        ];
    }

    protected function buildResult(string $idempotencyKey, array $payload, string $scenario): array
    {
        $keyCounter = 'mock_insurer_key.' . $idempotencyKey;
        $attempt = (int) Cache::get($keyCounter, 0);

        $providerReference = 'REF-' . strtoupper(substr(md5($idempotencyKey), 0, 8));
        $policyNumber = 'POL-' . strtoupper(substr(md5($idempotencyKey . now()->format('U')), 0, 10));

        if ($scenario === 'TEMPORARY_FAILURE') {
            if ($attempt === 0) {
                Cache::put($keyCounter, 1, 3600);
                throw new RuntimeException('Temporary insurer failure');
            }

            Cache::put($keyCounter, 2, 3600);

            return [
                'status' => 'issued',
                'provider_reference' => $providerReference,
                'policy_number' => $policyNumber,
            ];
        }

        if ($scenario === 'TIMEOUT_AFTER_ISSUE') {
            $result = [
                'status' => 'issued',
                'provider_reference' => $providerReference,
                'policy_number' => $policyNumber,
            ];
            Cache::put('mock_insurer_policies.' . $idempotencyKey, $result, 3600);
            throw new RuntimeException('Timeout while waiting for insurer response');
        }

        if ($scenario === 'DELAYED_CALLBACK') {
            return [
                'status' => 'pending',
                'provider_reference' => $providerReference,
                'policy_number' => null,
            ];
        }

        if ($scenario === 'DUPLICATE_CALLBACK') {
            return [
                'status' => 'pending',
                'provider_reference' => $providerReference,
                'policy_number' => null,
            ];
        }

        return [
            'status' => 'issued',
            'provider_reference' => $providerReference,
            'policy_number' => $policyNumber,
        ];
    }
}
