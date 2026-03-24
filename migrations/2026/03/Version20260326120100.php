<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add update_datetime to finance_entry_split';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry_split ADD update_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry_split DROP COLUMN update_datetime');
    }
}
