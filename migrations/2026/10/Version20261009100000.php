<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agenda entry availability: one answer per member per occurrence, and a flag to ask for it (#1000)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agenda_entry_availability (id CHAR(36) NOT NULL, occurrence_date DATE NOT NULL, answer VARCHAR(10) NOT NULL, answered_at DATETIME NOT NULL, agenda_entry_id CHAR(36) NOT NULL, membership_id CHAR(36) NOT NULL, UNIQUE INDEX unq_agenda_entry_availability (agenda_entry_id, occurrence_date, membership_id), INDEX IDX_278C554FBB6EFC26 (agenda_entry_id), INDEX IDX_278C554F1FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE agenda_entry_availability ADD CONSTRAINT FK_278C554FBB6EFC26 FOREIGN KEY (agenda_entry_id) REFERENCES agenda_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agenda_entry_availability ADD CONSTRAINT FK_278C554F1FB354CD FOREIGN KEY (membership_id) REFERENCES band_space_membership (id) ON DELETE CASCADE');
        // Entries that already exist are not asked, new ones are.
        $this->addSql('ALTER TABLE agenda_entry ADD ask_availability TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE agenda_entry ALTER ask_availability SET DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_entry DROP ask_availability');
        $this->addSql('ALTER TABLE agenda_entry_availability DROP FOREIGN KEY FK_278C554FBB6EFC26');
        $this->addSql('ALTER TABLE agenda_entry_availability DROP FOREIGN KEY FK_278C554F1FB354CD');
        $this->addSql('DROP TABLE agenda_entry_availability');
    }
}
