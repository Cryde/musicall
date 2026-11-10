<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The campaign and the referring site of the visit each web account was created in (#1369). No backfill:
 * nothing recorded them before.
 */
final class Version20261110150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fos_user.registration_utm_source, registration_utm_medium, registration_utm_campaign and registration_referrer_host';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD registration_utm_source VARCHAR(100) DEFAULT NULL, ADD registration_utm_medium VARCHAR(100) DEFAULT NULL, ADD registration_utm_campaign VARCHAR(100) DEFAULT NULL, ADD registration_referrer_host VARCHAR(253) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user DROP registration_utm_source, DROP registration_utm_medium, DROP registration_utm_campaign, DROP registration_referrer_host');
    }
}
