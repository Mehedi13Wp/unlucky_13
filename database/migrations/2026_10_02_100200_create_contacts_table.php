<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->text('value');
            $table->string('label')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'type', 'value']);
            $table->index(['lead_id', 'type']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('contact_discovered_at')->nullable()->after('qualified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('contact_discovered_at');
        });
    }
};
