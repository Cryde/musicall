<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508140734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_file table (foundation for the Files module — folder + currentVersion FKs land in #624 and #626).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_file (id CHAR(36) NOT NULL, original_name VARCHAR(255) NOT NULL, attached_source_type VARCHAR(20) DEFAULT NULL, attached_source_id CHAR(36) DEFAULT NULL, archive_datetime DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, created_by_id CHAR(36) DEFAULT NULL, INDEX IDX_1CDD155DE31C124A (band_space_id), INDEX IDX_1CDD155DB03A8386 (created_by_id), INDEX idx_band_space_file_band_archived (band_space_id, archive_datetime), INDEX idx_band_space_file_attached_source (attached_source_type, attached_source_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file ADD CONSTRAINT FK_1CDD155DE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_file ADD CONSTRAINT FK_1CDD155DB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file DROP FOREIGN KEY FK_1CDD155DE31C124A');
        $this->addSql('ALTER TABLE band_space_file DROP FOREIGN KEY FK_1CDD155DB03A8386');
        $this->addSql('DROP TABLE band_space_file');
    }
}
