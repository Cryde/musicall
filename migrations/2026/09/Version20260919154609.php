<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chat message gains an edit timestamp (#966).
 *
 * Nullable with no default and no backfill, because null is the answer for every row that exists:
 * before this, a message could not be edited. The column is what the « modifié » marker reads, so
 * "never edited" and "edited at some point" have to stay distinguishable rather than collapsing into
 * a creation date copied forward.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260919154609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the edit timestamp of a chat message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD update_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP update_datetime');
    }
}
