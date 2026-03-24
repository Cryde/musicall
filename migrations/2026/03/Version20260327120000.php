<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make finance_entry.date NOT NULL (backfill with creation_datetime)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE finance_entry SET date = creation_datetime WHERE date IS NULL');
        $this->addSql('ALTER TABLE finance_entry CHANGE date date DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry CHANGE date date DATETIME DEFAULT NULL');
    }
}
