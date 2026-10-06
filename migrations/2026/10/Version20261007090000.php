<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reports on content and users (#1116). The target is a type and an id, not a foreign key, so a
 * report and its snapshot survive the content being removed.
 */
final class Version20261007090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the report table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report (id CHAR(36) NOT NULL, target_type VARCHAR(30) NOT NULL, target_id VARCHAR(36) NOT NULL, reason VARCHAR(20) NOT NULL, details VARCHAR(500) DEFAULT NULL, snapshot_text LONGTEXT NOT NULL, snapshot_context JSON NOT NULL, creation_datetime DATETIME NOT NULL, resolution_datetime DATETIME DEFAULT NULL, outcome VARCHAR(30) DEFAULT NULL, reporter_id CHAR(36) NOT NULL, target_author_id CHAR(36) DEFAULT NULL, resolved_by_id CHAR(36) DEFAULT NULL, INDEX idx_report_target (target_type, target_id), INDEX idx_report_resolution (resolution_datetime), INDEX IDX_C42F7784E1CFE6F5 (reporter_id), INDEX IDX_C42F7784C2C18137 (target_author_id), INDEX IDX_C42F77846713A32B (resolved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784C2C18137 FOREIGN KEY (target_author_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F77846713A32B FOREIGN KEY (resolved_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784E1CFE6F5');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784C2C18137');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F77846713A32B');
        $this->addSql('DROP TABLE report');
    }
}
