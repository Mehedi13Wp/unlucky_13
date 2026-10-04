<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('from_name')->nullable();
            $table->string('smtp_host')->default('smtp.gmail.com');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_scheme', 20)->default('smtp');
            $table->string('smtp_username', 320);
            $table->text('smtp_password');
            $table->unsignedSmallInteger('daily_limit')->default(20);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
            $table->index(['user_id', 'enabled', 'id']);
        });

        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->foreignId('sender_account_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained('sender_accounts')
                ->nullOnDelete();
            $table->string('sender_address', 320)
                ->nullable()
                ->after('sender_account_id');

            $table->index(['sender_account_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->dropForeign(['sender_account_id']);
            $table->dropIndex(['sender_account_id', 'sent_at']);
            $table->dropColumn(['sender_account_id', 'sender_address']);
        });

        Schema::dropIfExists('sender_accounts');
    }
};
