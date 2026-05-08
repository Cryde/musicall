<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508124600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the legacy task_activity table; rows are now stored in band_space_activity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_activity DROP FOREIGN KEY `FK_ECB4E31610DAF24A`');
        $this->addSql('ALTER TABLE task_activity DROP FOREIGN KEY `FK_ECB4E3168DB60186`');
        $this->addSql('DROP TABLE task_activity');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_activity (id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, payload JSON DEFAULT NULL, creation_datetime DATETIME NOT NULL, task_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, actor_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_ECB4E3168DB60186 (task_id), INDEX IDX_ECB4E31610DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT `FK_ECB4E31610DAF24A` FOREIGN KEY (actor_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT `FK_ECB4E3168DB60186` FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
    }
}
