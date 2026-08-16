<?php

declare(strict_types=1);

use App\Enums\WalletCampaign\ThresholdScopeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add threshold_scope to wallet campaigns.
     *
     * Backfill strategy: campaigns that already have both dates are windowed;
     * everything else is lifetime. Lifetime campaigns must not carry dates
     * (validation rejects that), so stray dates on lifetime rows are cleared.
     */
    public function up(): void
    {
        Schema::table('wallet_campaigns', function (Blueprint $table) {
            $table->string('threshold_scope')
                ->default(ThresholdScopeEnum::LIFETIME->value)
                ->index()
                ->after('type')
                ->comment('Threshold measurement scope: lifetime (all history) or windowed (within campaign dates)');
        });

        DB::table('wallet_campaigns')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->update(['threshold_scope' => ThresholdScopeEnum::WINDOWED->value]);

        DB::table('wallet_campaigns')
            ->where('threshold_scope', ThresholdScopeEnum::LIFETIME->value)
            ->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('ends_at'))
            ->update([
                'starts_at' => null,
                'ends_at'   => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_campaigns', function (Blueprint $table) {
            $table->dropIndex(['threshold_scope']);
            $table->dropColumn('threshold_scope');
        });
    }
};
