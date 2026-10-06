<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Account suspension and the log of moderation actions (#1116).
 */
final class Version20261007091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account suspension and the moderation action log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD suspension_datetime DATETIME DEFAULT NULL, ADD suspension_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('CREATE TABLE moderation_action (id CHAR(36) NOT NULL, type VARCHAR(30) NOT NULL, reason VARCHAR(500) DEFAULT NULL, creation_datetime DATETIME NOT NULL, moderator_id CHAR(36) NOT NULL, target_user_id CHAR(36) DEFAULT NULL, report_id CHAR(36) DEFAULT NULL, INDEX IDX_B05D8128D0AFA354 (moderator_id), INDEX IDX_B05D81286C066AFE (target_user_id), INDEX IDX_B05D81284BD2A4C0 (report_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE moderation_action ADD CONSTRAINT FK_B05D8128D0AFA354 FOREIGN KEY (moderator_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE moderation_action ADD CONSTRAINT FK_B05D81286C066AFE FOREIGN KEY (target_user_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE moderation_action ADD CONSTRAINT FK_B05D81284BD2A4C0 FOREIGN KEY (report_id) REFERENCES report (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_action DROP FOREIGN KEY FK_B05D8128D0AFA354');
        $this->addSql('ALTER TABLE moderation_action DROP FOREIGN KEY FK_B05D81286C066AFE');
        $this->addSql('ALTER TABLE moderation_action DROP FOREIGN KEY FK_B05D81284BD2A4C0');
        $this->addSql('DROP TABLE moderation_action');
        $this->addSql('ALTER TABLE fos_user DROP suspension_datetime, DROP suspension_reason');
    }
}
