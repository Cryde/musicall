<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509083847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quota_bytes_override column on band_space (per-band file storage quota override).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space ADD quota_bytes_override BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space DROP quota_bytes_override');
    }
}
