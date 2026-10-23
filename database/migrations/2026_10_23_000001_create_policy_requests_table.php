<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('client_request_id');
            $table->string('agent_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->string('destination');
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code');
            $table->unsignedBigInteger('sum_insured');
            $table->decimal('premium', 12, 2)->nullable()->comment('Set once and never changed');
            $table->string('state')->default('RECEIVED');
            $table->string('mock_scenario')->default('SUCCESS');
            $table->string('provider_reference')->nullable()->unique();
            $table->string('policy_number')->nullable()->unique();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('last_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
