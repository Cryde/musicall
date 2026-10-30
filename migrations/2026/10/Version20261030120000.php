<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lyrics move out of the song into their own table, still linked to it (#1259).
 *
 * The song's lyrics columns stay for now: the previous release still reads them during the deploy.
 * #1263 drops them. A cleared text is copied too, so its revision carries on rather than resetting.
 */
final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move song lyrics into the band_space_lyrics table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_lyrics (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, song_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_DC6ABC1EA0BDB2F3 (song_id), INDEX IDX_DC6ABC1EE31C124A (band_space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_lyrics ADD CONSTRAINT FK_DC6ABC1EE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_lyrics ADD CONSTRAINT FK_DC6ABC1EA0BDB2F3 FOREIGN KEY (song_id) REFERENCES band_space_song (id) ON DELETE SET NULL');
        $this->addSql('INSERT INTO band_space_lyrics (id, title, content, version, creation_datetime, update_datetime, band_space_id, song_id) SELECT UUID(), title, lyrics, lyrics_version, NOW(), update_datetime, band_space_id, id FROM band_space_song WHERE lyrics IS NOT NULL OR lyrics_version > 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE band_space_song s INNER JOIN band_space_lyrics l ON l.song_id = s.id SET s.lyrics = l.content, s.lyrics_version = l.version');
        $this->addSql('ALTER TABLE band_space_lyrics DROP FOREIGN KEY FK_DC6ABC1EE31C124A');
        $this->addSql('ALTER TABLE band_space_lyrics DROP FOREIGN KEY FK_DC6ABC1EA0BDB2F3');
        $this->addSql('DROP TABLE band_space_lyrics');
    }
}
