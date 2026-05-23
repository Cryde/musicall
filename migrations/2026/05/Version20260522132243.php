<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522132243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurrence fields (frequency, until, monthly mode) to agenda_entry for Phase 1 of #621';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry ADD recurrence_frequency VARCHAR(20) DEFAULT NULL, ADD recurrence_until_date DATE DEFAULT NULL, ADD recurrence_monthly_mode VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry DROP recurrence_frequency, DROP recurrence_until_date, DROP recurrence_monthly_mode');
    }
}
