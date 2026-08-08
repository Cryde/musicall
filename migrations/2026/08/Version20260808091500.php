<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A dedicated counter rather than a comparison against `update_datetime`: that column is a second
 * precision DATETIME, and notes autosave every two seconds, so two writes inside the same second
 * are indistinguishable and the guard would wave the second one through.
 *
 * Existing rows take the default, which is the revision every open editor will read on its next
 * load, so nothing has to be backfilled.
 */
final class Version20260808091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a content revision counter to a band space note';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note ADD content_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note DROP content_version');
    }
}
