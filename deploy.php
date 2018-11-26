<?php

namespace Deployer;

require 'recipe/symfony4.php';
require 'vendor/deployer/recipes/recipe/npm.php';

// Project repository
set('repository', 'git@github.com:Cryde/musicall.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('keep_releases', 2);
set('ssh_multiplexing', true);

// Hosts
inventory('hosts.yml');

set(
    'env',
    function () {
        return [
            'APP_ENV'      => get('APP_ENV'),
            'DATABASE_URL' => get('DATABASE_URL'),
            'APP_SECRET'   => get('APP_SECRET'),
        ];
    }
);

// Tasks
desc('Build assets');
task(
    'assets:build',
    function () {
        run('cd {{release_path}} && {{bin/npm}} run build');
    }
);
after('npm:install', 'assets:build');

desc('Remove node_modules folder');
task(
    'assets:clean',
    function () {
        run('cd {{release_path}} && rm -rf node_modules');
    }
);
after('deploy:symlink', 'assets:clean');

desc('Restart PHP-FPM service');
task(
    'php-fpm:restart',
    function () {
        run('sudo service php7.2-fpm reload');
    }
);
after('deploy:symlink', 'php-fpm:restart');

// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');
after('deploy:update_code', 'npm:install');
