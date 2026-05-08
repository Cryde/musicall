<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508092902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE band_space_activity (id CHAR(36) NOT NULL, module VARCHAR(20) NOT NULL, resource_id CHAR(36) DEFAULT NULL, type VARCHAR(30) NOT NULL, payload JSON DEFAULT NULL, creation_datetime DATETIME NOT NULL, band_space_id CHAR(36) NOT NULL, actor_id CHAR(36) DEFAULT NULL, INDEX IDX_CD41CFC0E31C124A (band_space_id), INDEX IDX_CD41CFC010DAF24A (actor_id), INDEX idx_band_space_activity_feed (band_space_id, creation_datetime), INDEX idx_band_space_activity_resource (band_space_id, module, resource_id, creation_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_activity ADD CONSTRAINT FK_CD41CFC0E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_activity ADD CONSTRAINT FK_CD41CFC010DAF24A FOREIGN KEY (actor_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE band_space_activity DROP FOREIGN KEY FK_CD41CFC0E31C124A');
        $this->addSql('ALTER TABLE band_space_activity DROP FOREIGN KEY FK_CD41CFC010DAF24A');
        $this->addSql('DROP TABLE band_space_activity');
    }
}
