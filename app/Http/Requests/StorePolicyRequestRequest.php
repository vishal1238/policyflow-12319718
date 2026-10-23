<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePolicyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string'],
            'agent_id' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'destination' => ['required', 'in:DOMESTIC,INTERNATIONAL'],
            'trip_start' => ['required', 'date'],
            'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'in:BASIC,PLUS,PREMIUM'],
            'sum_insured' => ['required', 'integer', 'in:100000,300000,500000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_request_id.required' => 'The client_request_id field is required.',
            'agent_id.required' => 'The agent_id field is required.',
            'customer_name.required' => 'The customer_name field is required.',
            'date_of_birth.required' => 'The date_of_birth field is required.',
            'destination.required' => 'The destination field is required.',
            'destination.in' => 'Destination must be DOMESTIC or INTERNATIONAL.',
            'trip_start.required' => 'The trip_start field is required.',
            'trip_end.required' => 'The trip_end field is required.',
            'trip_end.after_or_equal' => 'trip_end must be on or after trip_start.',
            'plan_code.required' => 'The plan_code field is required.',
            'plan_code.in' => 'plan_code must be BASIC, PLUS or PREMIUM.',
            'sum_insured.required' => 'The sum_insured field is required.',
            'sum_insured.in' => 'sum_insured must be 100000, 300000 or 500000.',
        ];
    }
}
