<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An agenda entry gains an edit timestamp (#1046), the one band space module that had none, so an
 * edited gig can come up among the command palette's recents.
 *
 * Nullable with no backfill: null is the honest answer for every existing row, never edited as far as
 * anything recorded, and the recents fall back to the creation date for it.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260926101314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the edit timestamp of an agenda entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry ADD update_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry DROP update_datetime');
    }
}
