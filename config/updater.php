<?php

// config for Salahhusa9/Updater
use Database\Seeders\ScribeSeeder;

return [

    'git_path' => 'git',

    'repository_source' => \Salahhusa9\Updater\RepositorySource\GithubRepository::class,
    'github_token' => env('GITHUB_TOKEN'),
    'github_username' => env('GITHUB_USERNAME'),
    'github_repository' => env('GITHUB_REPOSITORY'),

    'github_timeout' => 100,

    'maintenance_mode' => true,
    'maintenance_mode_secret' => env('MAINTENANCE_MODE_SECRET', false),

    'before_update_pipelines' => [
        // you can add your own pipelines here
    ],

    // run php artisan migrate after update?
    'migrate' => true,

    // run seeders after update?
    'seeders' => [
        ScribeSeeder::class,
    ],

    // run php artisan cache:clear after update?
    'cache:clear' => true,

    // run php artisan view:clear after update?
    'view:clear' => false,

    // run php artisan config:clear after update?
    'config:clear' => true,

    // run php artisan route:clear after update?
    'route:clear' => true,

    // run php artisan optimize after update?
    'optimize' => true,

    'after_update_pipelines' => [
        'php artisan optimize:cleare',
        'php artisan cache:clear',
        'php artisan scribe:setup --fresh --seed',
        'php artisan migrate --fresh',
        'php artisan db:seed --class=ScribeSeeder',
        'php artisan permissions:sync',
        'php artisan optimize',
    ],

];
