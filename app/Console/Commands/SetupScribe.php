<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ScribeSimpleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDOException;

final class SetupScribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scribe:setup
                            {--fresh : Wipe the database and run migrations from scratch}
                            {--seed : Run database seeders after migrations}
                            {--seeder= : Specify a specific seeder class to run}
                            {--connection=sqlite_scribe : The database connection to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Api documentation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $configPath     = "database.connections.$connectionName";

        if (! Config::has($configPath)) {
            $this->error("Database connection '{$connectionName}' not found in config/database.php.");

            return Command::FAILURE;
        }

        $dbConfig = Config::get($configPath);
        $dbPath   = $dbConfig['database'] ?? null;

        // For file-based SQLite, ensure the file exists or can be created
        if ($dbConfig['driver'] === 'sqlite' && $dbPath && $dbPath !== ':memory:') {
            // Ensure the directory exists
            $dbDir = dirname($dbPath);
            if (! File::isDirectory($dbDir)) {
                File::makeDirectory($dbDir, 0755, true, true);
            }
            // Touch the file to ensure it exists for migrations if --fresh is not used
            if (! File::exists($dbPath) || $this->option('fresh')) {
                File::put($dbPath, ''); // Create or clear the file
                $this->info("SQLite database file created/cleared at: {$dbPath}");
            }
        } elseif ($dbConfig['driver'] === 'sqlite' && $dbPath === ':memory:') {
            $this->info("Using in-memory SQLite database for connection '{$connectionName}'.");
        }

        $this->info("Setting up database for Scribe using connection: {$connectionName}");

        // Temporarily set the default connection for Artisan commands
        // This is a common way, though be mindful if other parts of your app
        // might be sensitive to this change during the command's execution.
        // An alternative is to pass --database to migrate and seed commands explicitly.
        $originalDefaultConnection = DB::getDefaultConnection();
        Config::set('database.default', $connectionName); // Make sqlite_scribe the default for this process

        try {
            // Test connection (optional, but good for early feedback)
            DB::connection($connectionName)->getPdo();
            $this->info("Successfully connected to '{$connectionName}'.");
        } catch (PDOException $e) {
            $this->error("Could not connect to the database '{$connectionName}': ".$e->getMessage());
            Config::set('database.default', $originalDefaultConnection); // Reset default connection

            return Command::FAILURE;
        }

        $migrateCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $this->line("Running: php artisan {$migrateCommand} --database={$connectionName} --force");

        $migrationExitCode = Artisan::call($migrateCommand, [
            '--database' => $connectionName,
            '--force'    => true, // Important for non-interactive environments
        ]);

        if ($migrationExitCode === 0) {
            $this->info('Migrations completed successfully.');
        } else {
            $this->error('Migrations failed.');
            Config::set('database.default', $originalDefaultConnection); // Reset default connection

            return Command::FAILURE;
        }

        if ($this->option('seed') || $this->option('seeder')) {
            $seederClass = $this->option('seeder') ?: ScribeSimpleSeeder::class;
            $seedOptions = [
                '--database' => $connectionName,
                '--force'    => true,
            ];
            $seedOptions['--class'] = $seederClass;
            $this->line("Running: php artisan db:seed --database={$connectionName} --class={$seederClass} --force");

            $seedingExitCode = Artisan::call('db:seed', $seedOptions);

            if ($seedingExitCode === 0) {
                $this->info('Seeding completed successfully.');
            } else {
                $this->error('Seeding failed.');
                Config::set('database.default', $originalDefaultConnection); // Reset default connection

                return Command::FAILURE;
            }
        }

        // Restore the original default connection
        Config::set('database.default', $originalDefaultConnection);
        $this->info("Database setup for Scribe on '{$connectionName}' complete.");

        $this->call('scribe:generate');

        $this->info('API documentation generated successfully.');

        return Command::SUCCESS;
    }
}
