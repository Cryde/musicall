<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Who a chat message named with an `@`, one row per mentioned member (#964).
 *
 * The message content keeps its `@[uuid]` tokens, which say where in the sentence each name belongs,
 * so this table is not a second copy of them. It records the fact, which is what lets the renderer
 * name a member who has since left the band instead of printing `@inconnu` over their name in
 * history, and what makes "messages that mention me" a query rather than a regex over every row.
 *
 * Both foreign keys are RESTRICT, like every other one in the message domain. That is deliberate but
 * it has a consequence worth knowing: MessageThreadRepository::deleteByBandSpace() has to clear these
 * rows before the messages, or purging a space whose channel holds a single mention fails with error
 * 1451. Deleting an account needs nothing, because that only stamps deletion_datetime and leaves the
 * fos_user row in place.
 *
 * No backfill: before this migration no message could carry a mention.
 */
final class Version20260913084820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the members a Band Space chat message mentions';
    }

    /**
     * The unique index is what stops the same member being recorded twice for one message, which
     * `@[tous]` combined with naming somebody explicitly would otherwise do. It also gives InnoDB an
     * index for the message_id foreign key, so no extra one is created for it; mentioned_user_id gets
     * its own because "messages that mention me" reads that way round.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_mention (
            id CHAR(36) NOT NULL,
            message_id CHAR(36) NOT NULL,
            mentioned_user_id CHAR(36) NOT NULL,
            UNIQUE INDEX message_mention_unique (message_id, mentioned_user_id),
            INDEX idx_message_mention_user (mentioned_user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE message_mention ADD CONSTRAINT FK_728A63C1537A1329 FOREIGN KEY (message_id) REFERENCES message (id)');
        $this->addSql('ALTER TABLE message_mention ADD CONSTRAINT FK_728A63C1E6655814 FOREIGN KEY (mentioned_user_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_mention DROP FOREIGN KEY FK_728A63C1537A1329');
        $this->addSql('ALTER TABLE message_mention DROP FOREIGN KEY FK_728A63C1E6655814');
        $this->addSql('DROP TABLE message_mention');
    }
}
