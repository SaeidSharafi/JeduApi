<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/php-fpm.php';

set('application', 'jedu-api');
set('repository', 'ssh://git@ssh.git.jedu.ir:222/Jedu/Jedu-api.git');
set('git_tty', false);
set('keep_releases', 5);
set('http_user', 'www-data');
set('writable_mode', 'acl');
set('php_binary', 'php8.4');
set('bin/php', 'php8.4');
set('bin/composer', '{{bin/php}} $(which composer)');
set('migrate', false);
set('migrate-fresh', false);

add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/logs',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
]);

host('production')
    ->setHostname('185.141.133.113')
    ->setRemoteUser('deployer')
    ->setIdentityFile('~/.ssh/deploy_key')
    ->setDeployPath('/var/www/api.jedu.ir')
    ->set('branch', 'main');


task('scribe:generate', function () {
    run('cd {{release_path}} && {{php_binary}} artisan scribe:generate');
});

task('permission:update', function () {
    run('cd {{release_path}} && {{php_binary}} artisan permissions:sync --guard=staff');
    run('cd {{release_path}} && {{php_binary}} artisan permissions:sync --guard=user');
});
task('db:seed:demo', function () {
    if (get('migrate-fresh')) {
        run('cd {{release_path}} && {{php_binary}} artisan db:seed --class=DemoSeeder');
    }
});
task('scribe:generate', function () {
    run('cd {{release_path}} && {{php_binary}} artisan scribe:generate');
});
task('php-fpm:reload', function () {
    run('sudo systemctl reload php8.4-fpm');
});

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'deploy:writable',
    'artisan:cache:clear',
    'artisan:optimize:clear',
    'permission:update',
    'artisan:optimize',
    'db:seed:demo',
    'deploy:publish',
    'php-fpm:reload',
    'scribe:generate',
]);

after('artisan:storage:link', function () {
    if (get('migrate-fresh')) {
        invoke('artisan:migrate:fresh');
    }
    if (get('migrate')) {
        invoke('artisan:migrate');
    }
});

after('deploy', 'deploy:cleanup');
after('deploy:failed', 'deploy:unlock');
