<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802081656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_tech_rider (Tech Rider module core entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_tech_rider (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, archive_datetime DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, created_by_id CHAR(36) DEFAULT NULL, INDEX IDX_C4F3158FE31C124A (band_space_id), INDEX IDX_C4F3158FB03A8386 (created_by_id), INDEX idx_tech_rider_band_archive (band_space_id, archive_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_tech_rider ADD CONSTRAINT FK_C4F3158FE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_tech_rider ADD CONSTRAINT FK_C4F3158FB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider DROP FOREIGN KEY FK_C4F3158FE31C124A');
        $this->addSql('ALTER TABLE band_space_tech_rider DROP FOREIGN KEY FK_C4F3158FB03A8386');
        $this->addSql('DROP TABLE band_space_tech_rider');
    }
}
