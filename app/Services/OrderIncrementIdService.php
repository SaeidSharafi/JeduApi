<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Facades\DB;

final readonly class OrderIncrementIdService
{
    /**
     * Generate the next unique increment ID for an order.
     *
     * This method uses the orders table directly with a transaction lock
     * to ensure uniqueness even under concurrent requests.
     */
    public function generate(): string
    {
        return DB::transaction(function (): string {
            // Lock the last order row to prevent race conditions
            $lastOrder = Order::query()
                ->select('id', 'increment_id')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            // Calculate the next sequential number
            $nextNumber = $lastOrder
                ? $this->extractNumber($lastOrder->increment_id) + 1
                : config('order.increment_id.start_from');

            // Format according to the configured pattern
            return $this->format($nextNumber);
        });
    }

    /**
     * Extract the numeric portion from an increment ID.
     *
     * This handles different patterns by extracting the last numeric sequence.
     */
    private function extractNumber(string $incrementId): int
    {
        // For dated format (YYYYMMDD-NNNNNN), extract the part after the dash
        if (str_contains($incrementId, '-')) {
            $parts = explode('-', $incrementId);

            return (int) end($parts);
        }

        // For prefixed or simple format, extract all trailing digits
        preg_match('/(\d+)$/', $incrementId, $matches);

        return $matches ? (int) $matches[1] : 0;
    }

    /**
     * Format the increment ID according to the configured pattern.
     */
    private function format(int $number): string
    {
        $pattern = config('order.increment_id.pattern');
        $padding = config('order.increment_id.padding');
        $prefix  = config('order.increment_id.prefix');

        return match ($pattern) {
            'dated'    => $this->formatWithDate($number, $padding),
            'prefixed' => $prefix.mb_str_pad((string) $number, $padding, '0', STR_PAD_LEFT),
            default    => mb_str_pad((string) $number, $padding, '0', STR_PAD_LEFT),
        };
    }

    /**
     * Format with Verta (Persian/Jalali) date prefix.
     *
     * Format: YYYYMMDD-NNNNNN (e.g., 14040802-000001)
     */
    private function formatWithDate(int $number, int $padding): string
    {
        $verta      = Verta::now();
        $datePrefix = $verta->format('Ymd'); // e.g., 14040802

        $paddedNumber = mb_str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

        return "{$datePrefix}-{$paddedNumber}";
    }
}
