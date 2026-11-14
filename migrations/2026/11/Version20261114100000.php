<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The song's own lyrics columns, unread since #1259 moved them into band_space_lyrics (#1263).
 * down() copies the linked lyrics back, so the release before #1259 reads them again. Lyrics linked
 * to no song have no place in that schema and are not restored.
 */
final class Version20261114100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop band_space_song.lyrics and band_space_song.lyrics_version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_song DROP lyrics, DROP lyrics_version');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_song ADD lyrics LONGTEXT DEFAULT NULL, ADD lyrics_version INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE band_space_song s INNER JOIN band_space_lyrics l ON l.song_id = s.id SET s.lyrics = l.content, s.lyrics_version = l.version');
    }
}
