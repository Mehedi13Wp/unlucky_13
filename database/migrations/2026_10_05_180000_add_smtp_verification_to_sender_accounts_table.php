<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sender_accounts', function (Blueprint $table): void {
            $table->timestamp('smtp_verified_at')->nullable()->after('enabled')->index();
            $table->timestamp('smtp_verification_attempted_at')->nullable()->after('smtp_verified_at');
            $table->text('smtp_verification_error')->nullable()->after('smtp_verification_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('sender_accounts', function (Blueprint $table): void {
            $table->dropIndex(['smtp_verified_at']);
            $table->dropColumn([
                'smtp_verified_at',
                'smtp_verification_attempted_at',
                'smtp_verification_error',
            ]);
        });
    }
};
