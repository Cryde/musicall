<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Band Space objects a chat message points at, one row per reference (#970).
 *
 * Its own table rather than band_space_file_attachment, which couples a *file* to a source: that
 * coupling is what makes BandSpaceFileDeleteProcessor refuse to trash an attached file, and a message
 * mentioning a file in passing must not make it undeletable forever.
 *
 * `label` is the target's title, snapshotted when it was attached, which is what lets a message stay
 * readable after the task it named is deleted. It is also why this table needs neither a delete guard
 * nor an orphan prune command: an orphan row here is harmless, and pruning it would destroy the only
 * surviving record of what was linked.
 *
 * `target_id` carries no foreign key, deliberately: the column is polymorphic and points at one of
 * seven tables. message_id does, RESTRICT like everything else in the message domain, which is why
 * MessageThreadRepository::deleteByBandSpace() clears these rows before the messages. Without that,
 * purging a space whose channel holds one attachment fails with error 1451.
 *
 * No backfill: before this migration no message could carry an attachment.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260919103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the Band Space objects a chat message references';
    }

    /**
     * The unique index stops one message referencing the same object twice, and gives InnoDB an index
     * for the message_id foreign key so no extra one is created. The target pair gets its own, which
     * is the direction "what references this task" reads.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_attachment (
            id CHAR(36) NOT NULL,
            message_id CHAR(36) NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id CHAR(36) NOT NULL,
            label VARCHAR(255) NOT NULL,
            creation_datetime DATETIME NOT NULL,
            UNIQUE INDEX message_attachment_unique (message_id, target_type, target_id),
            INDEX idx_message_attachment_target (target_type, target_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE message_attachment ADD CONSTRAINT FK_B68FF524537A1329 FOREIGN KEY (message_id) REFERENCES message (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachment DROP FOREIGN KEY FK_B68FF524537A1329');
        $this->addSql('DROP TABLE message_attachment');
    }
}
