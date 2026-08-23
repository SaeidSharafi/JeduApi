<?php

declare(strict_types=1);

use App\Http\Middleware\HorizonBasicAuth;
use Illuminate\Support\Str;

return [
    'name'   => env('HORIZON_NAME'),
    'domain' => env('HORIZON_DOMAIN'),
    'path'   => env('HORIZON_PATH', 'horizon'),
    'use'    => 'default',
    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),
    'middleware' => ['web', HorizonBasicAuth::class],
    'auth'       => [
        'username' => env('HORIZON_USERNAME'),
        'password' => env('HORIZON_PASSWORD'),
    ],
    'waits' => [
        'redis:default' => 60,
    ],
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],
    'silenced'      => [],
    'silenced_tags' => [],
    'metrics'       => [
        'trim_snapshots' => [
            'job'   => 288,
            'queue' => 288,
        ],
    ],
    'fast_termination' => false,
    'memory_limit'     => 64,
    'defaults'         => [
        'supervisor-1' => [
            'connection'          => 'redis',
            'queue'               => ['default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 1,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-provisioning' => [
                'connection'          => 'redis',
                'queue'               => ['provisioning', 'notifications'],
                'balance'             => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses'        => 2,
                'maxProcesses'        => 10,
                'tries'               => 3,
                'timeout'             => 120,
                'nice'                => 0,
            ],
            'supervisor-default' => [
                'connection'   => 'redis',
                'queue'        => ['default'],
                'balance'      => 'simple',
                'minProcesses' => 2,
                'maxProcesses' => 5,
                'tries'        => 3,
                'timeout'      => 60,
                'nice'         => 0,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
