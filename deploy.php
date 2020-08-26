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
add('shared_dirs', [
    'public/images/publication/post',
    'public/images/publication/cover',
    'public/images/publication/featured',
    'public/images/wiki/artist',
    'public/images/gallery',
    'public/media/cache',
]);

add('shared_files', [
    '.env',
    '.env.local',
    'config/jwt/private.pem',
    'config/jwt/public.pem',
]);

// Hosts
inventory('hosts.yml');

// Tasks
task('npm:ci', function () {
    run("cd {{release_path}} && {{bin/npm}} ci");
});
desc('Build assets');
task(
    'assets:build',
    function () {
        run('cd {{release_path}} && {{bin/npm}} run build');
    }
);
after('npm:ci', 'assets:build');

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
        run('sudo service php7.4-fpm reload');
    }
);
after('deploy:symlink', 'php-fpm:restart');

// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');
after('deploy:shared', 'npm:ci');
