<?php

declare(strict_types=1);

namespace App\Console\Commands\Wallet;

use App\Actions\Admin\WalletCampaign\AllocateBirthdayGiftsAction;
use Illuminate\Console\Command;

final class AllocateBirthdayGiftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:allocate-birthday-gifts
                           {--dry-run : Show what would be allocated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Allocate active birthday gift campaigns to customers whose birthday is today';

    public function __construct(private AllocateBirthdayGiftsAction $allocateBirthdayGiftsAction)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Checking for customers with a birthday today...');

        $result = $this->allocateBirthdayGiftsAction->execute(dryRun: $dryRun);

        if ($result['allocated'] === 0) {
            $this->info('No birthday gifts to allocate.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would allocate {$result['allocated']} birthday gift(s).");
        } else {
            $this->info("Allocated {$result['allocated']} birthday gift(s).");
        }

        if ($result['skipped'] > 0) {
            $this->warn("Skipped {$result['skipped']} allocation(s) (campaign limits or ineligible).");
        }

        return self::SUCCESS;
    }
}
