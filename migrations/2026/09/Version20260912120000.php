<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A thread with no band_space_id is a direct message and keeps its participant rows. A thread with one
 * is a Band Space channel, whose members are derived from band_space_membership, so it has no
 * participant rows at all.
 *
 * The backfill mints ids with UUID(), which is version 1 where the application mints version 4. Nothing
 * reads the version, the route requirement accepts any, and #955 established that these ids carry no
 * ordering, so the difference is invisible. It is the only option MariaDB 10.11 offers: UUID_v4() does
 * not exist before 11.7.
 */
final class Version20260912120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scope a message thread to a Band Space and give every space its channel';
    }

    /**
     * Both indexes are created before the constraint so that InnoDB finds one to satisfy the foreign key
     * rather than adding a third of its own. The unique index is what forbids two channels with the same
     * name in one space; it says nothing about direct messages, where both columns are null and a unique
     * index reads every null as distinct.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread ADD name VARCHAR(100) DEFAULT NULL, ADD band_space_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_607D18CE31C124A ON message_thread (band_space_id)');
        $this->addSql('CREATE UNIQUE INDEX message_thread_band_space_name_unique ON message_thread (band_space_id, name)');
        $this->addSql('ALTER TABLE message_thread ADD CONSTRAINT FK_607D18CE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');

        // The channel is as old as the band, so it inherits the space's own creation datetime rather
        // than the moment this migration happened to run.
        $this->addSql(<<<'SQL'
            INSERT INTO message_thread (id, name, band_space_id, creation_datetime)
            SELECT UUID(), 'Général', id, creation_datetime
            FROM band_space
            SQL);
    }

    /**
     * The rows have to go before the column, because band_space_id is the only thing that identifies a
     * channel: dropping it first would leave threads with no participants sitting in the inbox query as
     * broken direct messages.
     *
     * Deleting them takes the cycle in order, last_message_id first, for the same reason
     * MessageThreadRepository::deleteByBandSpace() does: every foreign key here is RESTRICT and
     * message_thread.last_message_id points back into message. This is lossy once a channel carries
     * messages, which is the price of reversing a schema change that created the container for them.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE message_thread SET last_message_id = NULL WHERE band_space_id IS NOT NULL');
        $this->addSql('DELETE FROM message_thread_meta WHERE thread_id IN (SELECT id FROM message_thread WHERE band_space_id IS NOT NULL)');
        $this->addSql('DELETE FROM message WHERE thread_id IN (SELECT id FROM message_thread WHERE band_space_id IS NOT NULL)');
        $this->addSql('DELETE FROM message_participant WHERE thread_id IN (SELECT id FROM message_thread WHERE band_space_id IS NOT NULL)');
        $this->addSql('DELETE FROM message_thread WHERE band_space_id IS NOT NULL');

        $this->addSql('ALTER TABLE message_thread DROP FOREIGN KEY FK_607D18CE31C124A');
        $this->addSql('DROP INDEX IDX_607D18CE31C124A ON message_thread');
        $this->addSql('DROP INDEX message_thread_band_space_name_unique ON message_thread');
        $this->addSql('ALTER TABLE message_thread DROP name, DROP band_space_id');
    }
}
