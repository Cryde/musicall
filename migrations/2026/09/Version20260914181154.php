<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refresh tokens gain a family, required by gesdinet/jwt-refresh-token-bundle 3.0 (#1015).
 *
 * A token issued in place of another carries the family of the one it replaced, so a login and every
 * refresh descending from it share one value. That is what makes a session addressable: without it
 * "end this session" can only mean "delete this one token", which under `single_use` has usually
 * been replaced already.
 *
 * **Apply this before deploying the code that needs it.** Doctrine reads every mapped field, so the
 * refresh endpoint fails while the columns are missing, and the symptom is every member being logged
 * out an hour later (#1008). Both columns are nullable and 2.2.2 ignores columns it does not map, so
 * running this against the old code is safe and leaves no window where refreshing is broken. Deployer
 * runs no migrations, so on production this is a manual step.
 *
 * Existing rows keep a null family, read as "this token belongs to no chain". They carry on working
 * and their replacements get families normally, so there is nothing to backfill.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and produced four unrelated statements alongside these.
 */
final class Version20260914181154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the refresh token family columns for jwt-refresh-token-bundle 3.0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD family VARCHAR(32) DEFAULT NULL, ADD family_valid DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_refresh_tokens_family ON refresh_tokens (family)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_refresh_tokens_family ON refresh_tokens');
        $this->addSql('ALTER TABLE refresh_tokens DROP family, DROP family_valid');
    }
}
