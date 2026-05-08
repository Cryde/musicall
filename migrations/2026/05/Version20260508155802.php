<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508155802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_file_version table and add current_version_id FK on band_space_file.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_file_version (id CHAR(36) NOT NULL, version_number INT NOT NULL, mime_type VARCHAR(191) NOT NULL, size INT DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_file_id CHAR(36) NOT NULL, created_by_id CHAR(36) DEFAULT NULL, INDEX IDX_3B2112B2CDF230B2 (band_space_file_id), INDEX IDX_3B2112B2B03A8386 (created_by_id), INDEX idx_band_space_file_version_lookup (band_space_file_id, version_number), UNIQUE INDEX unique_band_space_file_version_number (band_space_file_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file_version ADD CONSTRAINT FK_3B2112B2CDF230B2 FOREIGN KEY (band_space_file_id) REFERENCES band_space_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_file_version ADD CONSTRAINT FK_3B2112B2B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE band_space_file ADD current_version_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_file ADD CONSTRAINT FK_1CDD155D9407EE77 FOREIGN KEY (current_version_id) REFERENCES band_space_file_version (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1CDD155D9407EE77 ON band_space_file (current_version_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file DROP FOREIGN KEY FK_1CDD155D9407EE77');
        $this->addSql('DROP INDEX IDX_1CDD155D9407EE77 ON band_space_file');
        $this->addSql('ALTER TABLE band_space_file DROP current_version_id');

        $this->addSql('ALTER TABLE band_space_file_version DROP FOREIGN KEY FK_3B2112B2CDF230B2');
        $this->addSql('ALTER TABLE band_space_file_version DROP FOREIGN KEY FK_3B2112B2B03A8386');
        $this->addSql('DROP TABLE band_space_file_version');
    }
}
