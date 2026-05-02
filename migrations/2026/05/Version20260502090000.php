<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task.completed_datetime to track when a task was moved to Done; backfill existing Done tasks from update_datetime (fallback creation_datetime)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD completed_datetime DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql("UPDATE task SET completed_datetime = COALESCE(update_datetime, creation_datetime) WHERE status = 'done' AND completed_datetime IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP completed_datetime');
    }
}
