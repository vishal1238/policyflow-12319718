<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'premium',
        'state',
        'mock_scenario',
        'provider_reference',
        'policy_number',
        'attempt_count',
        'last_response',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'trip_start' => 'date',
        'trip_end' => 'date',
        'premium' => 'decimal:2',
        'last_response' => 'array',
    ];

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class);
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class);
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class);
    }
}
