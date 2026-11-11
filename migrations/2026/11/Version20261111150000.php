<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The language each account was created in (#1371). Existing accounts are backfilled with their current locale,
 * the closest record there is: a member who switched language since cannot be told apart.
 */
final class Version20261111150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fos_user.registration_locale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD registration_locale VARCHAR(10) DEFAULT NULL');
        $this->addSql('UPDATE fos_user SET registration_locale = locale');
        $this->addSql('ALTER TABLE fos_user MODIFY registration_locale VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user DROP registration_locale');
    }
}
