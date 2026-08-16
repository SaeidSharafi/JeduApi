<?php

declare(strict_types=1);

namespace App\Console\Commands\Wallet;

use App\Actions\Wallet\ReclaimExpiredGiftsAction;
use Illuminate\Console\Command;

final class ReclaimExpiredGiftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reclaim-expired-gifts
                           {--dry-run : Show what would be reclaimed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reclaim unspent gift balance that has passed its expiry date';

    public function __construct(private ReclaimExpiredGiftsAction $reclaimExpiredGiftsAction)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Checking for expired gift balances...');

        $result = $this->reclaimExpiredGiftsAction->execute(dryRun: $dryRun);

        if ($result['reclaimed'] === 0) {
            $this->info('No expired gift balances found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would reclaim {$result['reclaimed']} gift(s).");
        } else {
            $this->info("Reclaimed {$result['reclaimed']} gift(s).");
        }

        return self::SUCCESS;
    }
}
