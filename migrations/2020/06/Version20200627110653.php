<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200627110653 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE musician_announce (id INT AUTO_INCREMENT NOT NULL, author_id INT NOT NULL, instrument_id INT NOT NULL, creation_datetime DATETIME NOT NULL, type SMALLINT NOT NULL, location_name VARCHAR(255) NOT NULL, longitude VARCHAR(255) NOT NULL, latitude VARCHAR(255) NOT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_4E88BA9BF675F31B (author_id), INDEX IDX_4E88BA9BCF11D9C (instrument_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE musician_announce_style (musician_announce_id INT NOT NULL, style_id INT NOT NULL, INDEX IDX_C6BA9CDD6A4EDF4F (musician_announce_id), INDEX IDX_C6BA9CDDBACD6074 (style_id), PRIMARY KEY(musician_announce_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE musician_announce ADD CONSTRAINT FK_4E88BA9BF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE musician_announce ADD CONSTRAINT FK_4E88BA9BCF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id)');
        $this->addSql('ALTER TABLE musician_announce_style ADD CONSTRAINT FK_C6BA9CDD6A4EDF4F FOREIGN KEY (musician_announce_id) REFERENCES musician_announce (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE musician_announce_style ADD CONSTRAINT FK_C6BA9CDDBACD6074 FOREIGN KEY (style_id) REFERENCES attribute_style (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE musician_announce_style DROP FOREIGN KEY FK_C6BA9CDD6A4EDF4F');
        $this->addSql('DROP TABLE musician_announce');
        $this->addSql('DROP TABLE musician_announce_style');
    }
}
