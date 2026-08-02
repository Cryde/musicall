<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802113802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_tech_rider_section (ordered rich text sections of a rider)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_tech_rider_section (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, content JSON DEFAULT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, tech_rider_id CHAR(36) NOT NULL, INDEX IDX_69559A219FF2F0D (tech_rider_id), INDEX idx_rider_section_rider_position (tech_rider_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_tech_rider_section ADD CONSTRAINT FK_69559A219FF2F0D FOREIGN KEY (tech_rider_id) REFERENCES band_space_tech_rider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_section DROP FOREIGN KEY FK_69559A219FF2F0D');
        $this->addSql('DROP TABLE band_space_tech_rider_section');
    }
}
