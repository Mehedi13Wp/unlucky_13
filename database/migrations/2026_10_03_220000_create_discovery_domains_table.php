<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 253)->unique();
            $table->text('canonical_url')->nullable();
            $table->foreignId('first_lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();
            $table->foreignId('first_campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();
            $table->string('source', 80)->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
        });

        DB::table('leads')
            ->select([
                'id',
                'campaign_id',
                'domain',
                'website_url',
                'source',
                'source_url',
                'created_at',
                'updated_at',
            ])
            ->whereNotNull('domain')
            ->where('domain', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($leads): void {
                foreach ($leads as $lead) {
                    $domain = strtolower(trim((string) $lead->domain));

                    if ($domain === '') {
                        continue;
                    }

                    DB::table('discovery_domains')->insertOrIgnore([
                        'domain' => $domain,
                        'canonical_url' => $lead->website_url,
                        'first_lead_id' => $lead->id,
                        'first_campaign_id' => $lead->campaign_id,
                        'source' => $lead->source,
                        'source_url' => $lead->source_url,
                        'first_seen_at' => $lead->created_at,
                        'last_seen_at' => $lead->updated_at ?? $lead->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_domains');
    }
};
