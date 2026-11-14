<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'contrib/npm.php';

// How many days a tab opened before a deploy keeps finding the chunks of its build (#1105). Set
// before the import, so hosts.yml can override it.
set('assets_retention_days', 7);

// Hosts
import('hosts.yml');

desc('Print when the deploy finished');
task('deploy:date', function () {
    info('deployed on ' . runLocally('date "+%Y-%m-%d %H:%M:%S %Z"'));
})->hidden();

after('deploy:success', 'deploy:date');

// Each release builds its own public/build, so once the symlink switches, a tab opened before the
// deploy asks for chunks that are gone (#1105). The hashed files of the release being served are
// carried over: current, not previous_release, which after a failed deploy names a release that never
// went live. No overwrite, and their date kept, so a file only the old builds emitted ages out after
// the retention while one the new build emitted is fresh. Only assets/: manifest.json,
// entrypoints.json and version.json describe this release alone. A first deploy has nothing to carry.
desc('Carry the served release\'s built assets into this one, for tabs opened before the deploy');
task('assets:carry', function () {
    if (!test('[ -d {{current_path}}/public/build/assets ]')) {
        return;
    }
    run('cp -R -n -p {{current_path}}/public/build/assets/. {{release_path}}/public/build/assets/');
    run('find {{release_path}}/public/build/assets -type f -mtime +{{assets_retention_days}} -delete');
});

after('assets:build', 'assets:carry');
