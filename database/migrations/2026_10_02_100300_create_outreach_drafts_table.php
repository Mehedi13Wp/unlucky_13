<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40);
            $table->text('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('personalization_notes')->nullable();
            $table->string('status', 40)->default('pending_review')->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_drafts');
    }
};
