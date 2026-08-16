<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the unspent slice of each gift credit so gift-first FIFO
     * consumption and later expiry reclaims can address the right transaction.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('remaining_amount')
                ->nullable()
                ->after('amount')
                ->comment('Unspent slice of a gift/bonus credit; null for non-gift transactions');
        });

        $this->backfillRemainingAmounts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('remaining_amount');
        });
    }

    /**
     * Backfill remaining_amount by replaying FIFO consumption: gifts are spent
     * oldest-first, so the already-consumed slice (total credits minus the
     * wallet's current gift_balance) is charged against the oldest credits and
     * the remainder is left unspent on the newest.
     */
    private function backfillRemainingAmounts(): void
    {
        $walletIds = DB::table('wallet_transactions')
            ->whereIn('type', ['gift', 'bonus'])
            ->where('amount', '>', 0)
            ->distinct()
            ->pluck('wallet_id');

        $walletIds->each(function (int $walletId): void {
            $giftBalance = (int) DB::table('wallets')->where('id', $walletId)->value('gift_balance');

            $giftCredits = DB::table('wallet_transactions')
                ->where('wallet_id', $walletId)
                ->whereIn('type', ['gift', 'bonus'])
                ->where('amount', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'amount']);

            $toConsume = max(0, (int) $giftCredits->sum('amount') - $giftBalance);

            foreach ($giftCredits as $credit) {
                if ($toConsume <= 0) {
                    DB::table('wallet_transactions')
                        ->where('id', $credit->id)
                        ->update(['remaining_amount' => (int) $credit->amount]);

                    continue;
                }

                $consume = min((int) $credit->amount, $toConsume);
                $toConsume -= $consume;

                DB::table('wallet_transactions')
                    ->where('id', $credit->id)
                    ->update(['remaining_amount' => (int) $credit->amount - $consume]);
            }
        });
    }
};
