<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Zenstruck\Foundry\Persistence\ResetDatabase\ResetDatabaseManager;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// bin/test-parallel brings every worker database up to date beforehand. Foundry would otherwise drop and rebuild
// the schema once per worker, and MariaDB serialises that DDL: sixteen rebuilds cost more than the tests themselves.
if (getenv('TEST_SCHEMA_READY')) {
    (new ReflectionProperty(ResetDatabaseManager::class, 'hasDatabaseBeenReset'))->setValue(null, true);
}
