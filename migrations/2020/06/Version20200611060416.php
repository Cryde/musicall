<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200611060416 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE wiki_artist_cover (id INT AUTO_INCREMENT NOT NULL, artist_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F99C115B7970CF8 (artist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wiki_artist_cover ADD CONSTRAINT FK_6F99C115B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)');
        $this->addSql('ALTER TABLE artist ADD cover_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT FK_1599687922726E9 FOREIGN KEY (cover_id) REFERENCES wiki_artist_cover (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1599687922726E9 ON artist (cover_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE artist DROP FOREIGN KEY FK_1599687922726E9');
        $this->addSql('DROP TABLE wiki_artist_cover');
        $this->addSql('DROP INDEX UNIQ_1599687922726E9 ON artist');
        $this->addSql('ALTER TABLE artist DROP cover_id');
    }
}
