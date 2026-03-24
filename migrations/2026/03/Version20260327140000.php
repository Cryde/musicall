<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create finance_recurrence table, modify finance_entry (remove isRecurring/recurrenceInterval, add recurrence_id FK)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE finance_recurrence (id CHAR(36) NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount INT NOT NULL, scope VARCHAR(255) NOT NULL, `interval` VARCHAR(255) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, category_id CHAR(36) NOT NULL, INDEX IDX_finance_recurrence_category (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE finance_recurrence ADD CONSTRAINT FK_finance_recurrence_category FOREIGN KEY (category_id) REFERENCES finance_category (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE finance_entry ADD recurrence_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE finance_entry ADD CONSTRAINT FK_finance_entry_recurrence FOREIGN KEY (recurrence_id) REFERENCES finance_recurrence (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_finance_entry_recurrence ON finance_entry (recurrence_id)');

        $this->addSql('ALTER TABLE finance_entry DROP COLUMN is_recurring');
        $this->addSql('ALTER TABLE finance_entry DROP COLUMN recurrence_interval');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry ADD is_recurring TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE finance_entry ADD recurrence_interval VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE finance_entry DROP FOREIGN KEY FK_finance_entry_recurrence');
        $this->addSql('DROP INDEX IDX_finance_entry_recurrence ON finance_entry');
        $this->addSql('ALTER TABLE finance_entry DROP COLUMN recurrence_id');

        $this->addSql('DROP TABLE finance_recurrence');
    }
}
