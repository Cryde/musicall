<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222115726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE band_space_note (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, content JSON DEFAULT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, INDEX IDX_5FFFD959E31C124A (band_space_id), INDEX IDX_5FFFD959727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_note ADD CONSTRAINT FK_5FFFD959E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_note ADD CONSTRAINT FK_5FFFD959727ACA70 FOREIGN KEY (parent_id) REFERENCES band_space_note (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE band_space_note DROP FOREIGN KEY FK_5FFFD959E31C124A');
        $this->addSql('ALTER TABLE band_space_note DROP FOREIGN KEY FK_5FFFD959727ACA70');
        $this->addSql('DROP TABLE band_space_note');
    }
}
