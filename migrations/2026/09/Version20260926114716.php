<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A song gains its lyrics, in ChordPro, and the revision a save must name so two members editing at
 * once cannot silently overwrite each other (#1055).
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260926114716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the lyrics of a song and their revision';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_song ADD lyrics LONGTEXT DEFAULT NULL, ADD lyrics_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_song DROP lyrics, DROP lyrics_version');
    }
}
