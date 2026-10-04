<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * FCM registration tokens of the mobile app, one per device (#1109).
 */
final class Version20261003180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the device_token table for mobile push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE device_token (id CHAR(36) NOT NULL, token VARCHAR(512) NOT NULL, platform VARCHAR(20) NOT NULL, creation_datetime DATETIME NOT NULL, last_seen_datetime DATETIME NOT NULL, user_id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_99B2415C5F37A13B (token), INDEX IDX_99B2415CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_99B2415CA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_token DROP FOREIGN KEY FK_99B2415CA76ED395');
        $this->addSql('DROP TABLE device_token');
    }
}
