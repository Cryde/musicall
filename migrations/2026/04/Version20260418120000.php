<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename task.archived_at to task.archive_datetime';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_task_archived_at ON task');
        $this->addSql('ALTER TABLE task CHANGE archived_at archive_datetime DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_task_archive_datetime ON task (archive_datetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_task_archive_datetime ON task');
        $this->addSql('ALTER TABLE task CHANGE archive_datetime archived_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_task_archived_at ON task (archived_at)');
    }
}
