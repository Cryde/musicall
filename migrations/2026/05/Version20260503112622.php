<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503112622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create agenda_entry table for band-space manual agenda entries';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agenda_entry (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, event_datetime DATETIME NOT NULL, creation_datetime DATETIME NOT NULL, band_space_id CHAR(36) NOT NULL, creator_id CHAR(36) DEFAULT NULL, INDEX IDX_7B19C9EEE31C124A (band_space_id), INDEX IDX_7B19C9EE61220EA6 (creator_id), INDEX idx_agenda_entry_event_datetime (event_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE agenda_entry ADD CONSTRAINT FK_7B19C9EEE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agenda_entry ADD CONSTRAINT FK_7B19C9EE61220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agenda_entry DROP FOREIGN KEY FK_7B19C9EEE31C124A');
        $this->addSql('ALTER TABLE agenda_entry DROP FOREIGN KEY FK_7B19C9EE61220EA6');
        $this->addSql('DROP TABLE agenda_entry');
    }
}
