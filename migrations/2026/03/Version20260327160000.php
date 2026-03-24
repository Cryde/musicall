<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status and left_datetime to band_space_membership for soft-delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE band_space_membership ADD status VARCHAR(255) NOT NULL DEFAULT 'active'");
        $this->addSql('ALTER TABLE band_space_membership ADD left_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_membership DROP COLUMN status');
        $this->addSql('ALTER TABLE band_space_membership DROP COLUMN left_datetime');
    }
}
