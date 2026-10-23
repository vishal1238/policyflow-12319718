<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceAttempt extends Model
{
    protected $fillable = [
        'policy_request_id',
        'attempt_no',
        'outcome',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
