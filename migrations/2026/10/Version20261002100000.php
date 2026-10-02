<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A patch row can be a stereo pair (#1099): one source on its channel and the next. Existing rows
 * are mono, which is what the default says.
 */
final class Version20261002100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the stereo flag to tech rider patch rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_patch_row ADD stereo TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_patch_row DROP stereo');
    }
}
