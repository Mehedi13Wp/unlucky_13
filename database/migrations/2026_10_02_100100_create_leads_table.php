<?php

use App\Enums\LeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->text('website_url')->nullable();
            $table->string('domain')->nullable();
            $table->string('industry')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->text('source_url')->nullable();
            $table->string('status', 40)->default(LeadStatus::Discovered->value)->index();
            $table->unsignedTinyInteger('qualification_score')->nullable();
            $table->text('qualification_reason')->nullable();
            $table->json('observed_signals')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'domain']);
            $table->index(['campaign_id', 'status']);
            $table->index(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
