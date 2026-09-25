<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chat message can carry a voice note (#974), with its duration as ffprobe measured it and the
 * loudness peaks its waveform is drawn from.
 *
 * No foreign key, for the reason image_file_id has none: the file can be purged from the Files trash
 * while the message stays, and the id outliving it is what lets the bubble say the note is gone.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260925204340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the voice note a chat message carries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD voice_note_file_id CHAR(36) DEFAULT NULL, ADD voice_note_duration_seconds SMALLINT DEFAULT NULL, ADD voice_note_peaks JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP voice_note_file_id, DROP voice_note_duration_seconds, DROP voice_note_peaks');
    }
}
