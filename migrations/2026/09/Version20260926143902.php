<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A setlist gains the duration the band means it to last (#1061), in seconds. Nullable with no
 * backfill: no existing setlist has a target.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260926143902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the duration target of a setlist';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_setlist ADD target_duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_setlist DROP target_duration');
    }
}
