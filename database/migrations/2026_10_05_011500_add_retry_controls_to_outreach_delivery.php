<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt_count')->default(0)->after('error_message');
            $table->timestamp('retry_at')->nullable()->after('attempted_at')->index();
        });

        Schema::table('delivery_automation_settings', function (Blueprint $table) {
            $table->timestamp('next_attempt_at')->nullable()->after('last_attempt_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_automation_settings', function (Blueprint $table) {
            $table->dropIndex(['next_attempt_at']);
            $table->dropColumn('next_attempt_at');
        });

        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->dropIndex(['retry_at']);
            $table->dropColumn(['attempt_count', 'retry_at']);
        });
    }
};
