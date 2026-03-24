<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change finance_entry.date from DATETIME to DATE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry MODIFY COLUMN date DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry MODIFY COLUMN date DATETIME NOT NULL');
    }
}
