<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->default('email');
            $table->string('value', 320);
            $table->string('reason', 120)->nullable();
            $table->string('source', 120)->nullable();
            $table->timestamp('suppressed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index(['type', 'released_at']);
        });

        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outreach_draft_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 40);
            $table->string('recipient', 320);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 40)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'sent_at']);
            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('suppressions');
    }
};
