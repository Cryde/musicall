<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pinning a Band Space chat message to the top of its channel (#969).
 *
 * Two columns on `message` rather than a pin table: a message is pinned at most once, so a table
 * would mean a join and a uniqueness constraint to express what a nullable timestamp already says.
 * The pinned list is then `WHERE thread_id = ? AND pinned_datetime IS NOT NULL`, which is what
 * idx_message_thread_pinned serves.
 *
 * `pinned_by_id` is RESTRICT like every other foreign key in the message domain, and needs nothing
 * from the account deletion path: DeleteAccountProcedure anonymises the fos_user row in place and
 * keeps its primary key. It adds no new reference *to* `message`, so
 * MessageThreadRepository::deleteByBandSpace() and app:band-space:purge are unaffected.
 *
 * No backfill: before this migration nothing could be pinned.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries unrelated drift along with it.
 */
final class Version20260919120909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a Band Space chat message be pinned to the top of its channel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD pinned_datetime DATETIME DEFAULT NULL, ADD pinned_by_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F59662AC1 FOREIGN KEY (pinned_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX idx_message_thread_pinned ON message (thread_id, pinned_datetime)');
        $this->addSql('CREATE INDEX IDX_B6BD307F59662AC1 ON message (pinned_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F59662AC1');
        $this->addSql('DROP INDEX IDX_B6BD307F59662AC1 ON message');
        $this->addSql('DROP INDEX idx_message_thread_pinned ON message');
        $this->addSql('ALTER TABLE message DROP pinned_datetime, DROP pinned_by_id');
    }
}
