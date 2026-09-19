<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chat message can be deleted, and it leaves a tombstone behind (#967).
 *
 * Nullable, so every existing row reads as "not deleted" and there is nothing to backfill. The
 * column is the only schema change the feature needs: the delete empties `content` and removes the
 * `message_mention` rows, both of which already exist.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260919112342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.deletion_datetime for the chat soft delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD deletion_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP deletion_datetime');
    }
}
