<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260524141250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add agenda_entry_exception table (cancelled occurrences for scoped delete on recurring agenda entries)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agenda_entry_exception (id CHAR(36) NOT NULL, occurrence_date DATE NOT NULL, creation_datetime DATETIME NOT NULL, agenda_entry_id CHAR(36) NOT NULL, INDEX IDX_82810970BB6EFC26 (agenda_entry_id), UNIQUE INDEX unq_agenda_entry_exception_date (agenda_entry_id, occurrence_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE agenda_entry_exception ADD CONSTRAINT FK_82810970BB6EFC26 FOREIGN KEY (agenda_entry_id) REFERENCES agenda_entry (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry_exception DROP FOREIGN KEY FK_82810970BB6EFC26');
        $this->addSql('DROP TABLE agenda_entry_exception');
    }
}
