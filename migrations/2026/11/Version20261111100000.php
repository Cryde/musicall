<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The platform and app version members use (#1370): the last one on the account, and one row per member,
 * day and platform. No backfill: nothing recorded them before.
 */
final class Version20261111100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fos_user.last_platform and last_app_version, and the user_daily_activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD last_platform VARCHAR(20) DEFAULT NULL, ADD last_app_version VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE TABLE user_daily_activity (id CHAR(36) NOT NULL, day DATE NOT NULL, platform VARCHAR(20) NOT NULL, app_version VARCHAR(32) DEFAULT NULL, user_id CHAR(36) NOT NULL, INDEX idx_user_daily_activity_day_platform_user (day, platform, user_id), UNIQUE INDEX user_daily_activity_unique (user_id, day, platform), INDEX IDX_7DFE01D7A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_daily_activity ADD CONSTRAINT FK_7DFE01D7A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_daily_activity');
        $this->addSql('ALTER TABLE fos_user DROP last_platform, DROP last_app_version');
    }
}
