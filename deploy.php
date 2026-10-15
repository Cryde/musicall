<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'contrib/npm.php';

// Hosts
import('hosts.yml');

desc('Print when the deploy finished');
task('deploy:date', function () {
    info('deployed on ' . runLocally('date "+%Y-%m-%d %H:%M:%S %Z"'));
})->hidden();

after('deploy:success', 'deploy:date');
