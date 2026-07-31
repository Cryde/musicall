<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730073334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add band_space.deletion_scheduled_datetime for the 30-day deletion grace period (#748)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space ADD deletion_scheduled_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space DROP deletion_scheduled_datetime');
    }
}
