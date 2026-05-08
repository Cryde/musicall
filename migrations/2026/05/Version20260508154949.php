<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508154949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_folder table and add folder_id FK on band_space_file.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_folder (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, created_by_id CHAR(36) DEFAULT NULL, INDEX IDX_B12D9B5E31C124A (band_space_id), INDEX IDX_B12D9B5727ACA70 (parent_id), INDEX IDX_B12D9B5B03A8386 (created_by_id), INDEX idx_band_space_folder_parent (band_space_id, parent_id), UNIQUE INDEX unique_band_space_folder_sibling_name (band_space_id, parent_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_folder ADD CONSTRAINT FK_B12D9B5E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_folder ADD CONSTRAINT FK_B12D9B5727ACA70 FOREIGN KEY (parent_id) REFERENCES band_space_folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_folder ADD CONSTRAINT FK_B12D9B5B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE band_space_file ADD folder_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_file ADD CONSTRAINT FK_1CDD155D162CB942 FOREIGN KEY (folder_id) REFERENCES band_space_folder (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1CDD155D162CB942 ON band_space_file (folder_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file DROP FOREIGN KEY FK_1CDD155D162CB942');
        $this->addSql('DROP INDEX IDX_1CDD155D162CB942 ON band_space_file');
        $this->addSql('ALTER TABLE band_space_file DROP folder_id');

        $this->addSql('ALTER TABLE band_space_folder DROP FOREIGN KEY FK_B12D9B5E31C124A');
        $this->addSql('ALTER TABLE band_space_folder DROP FOREIGN KEY FK_B12D9B5727ACA70');
        $this->addSql('ALTER TABLE band_space_folder DROP FOREIGN KEY FK_B12D9B5B03A8386');
        $this->addSql('DROP TABLE band_space_folder');
    }
}
