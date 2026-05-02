<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shrink task.status and task.priority from VARCHAR(255) to VARCHAR(20); enum values are at most 11 chars and both columns are indexed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task MODIFY status VARCHAR(20) NOT NULL, MODIFY priority VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task MODIFY status VARCHAR(255) NOT NULL, MODIFY priority VARCHAR(255) NOT NULL');
    }
}
