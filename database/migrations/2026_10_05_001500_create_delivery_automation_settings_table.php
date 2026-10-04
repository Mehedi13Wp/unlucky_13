<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false)->index();
            $table->unsignedSmallInteger('global_daily_limit')->default(20);
            $table->unsignedSmallInteger('interval_minutes')->default(15);
            $table->unsignedSmallInteger('session_hours')->default(5);
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ends_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_status', 80)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_automation_settings');
    }
};
