<?php

use App\Enums\CampaignStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('service_offer');
            $table->string('target_industry')->nullable();
            $table->json('target_countries')->nullable();
            $table->json('target_cities')->nullable();
            $table->json('qualification_rules')->nullable();
            $table->unsignedSmallInteger('daily_outreach_limit')->default(20);
            $table->string('status', 30)->default(CampaignStatus::Draft->value)->index();
            $table->boolean('review_required')->default(true);
            $table->boolean('auto_send')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
