<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lyrics can go to the setlist module's Corbeille (#1260).
 */
final class Version20261030140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archive_datetime to band_space_lyrics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_lyrics ADD archive_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_lyrics DROP archive_datetime');
    }
}
