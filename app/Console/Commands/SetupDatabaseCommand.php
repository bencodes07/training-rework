<?php

namespace App\Console\Commands;

use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-database {--fresh : Run fresh migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup the database with migrations and seeders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up the database...');

        if ($this->option('fresh')) {
            $this->info('Running fresh migrations...');
            Artisan::call('migrate:fresh', [], $this->output);
        } else {
            $this->info('Running migrations...');
            Artisan::call('migrate', [], $this->output);
        }

        $this->info('Seeding roles...');
        Artisan::call('db:seed', ['--class' => RoleSeeder::class], $this->output);

        $this->info('Database setup complete!');

        return 0;
    }
}