<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes on finance tables for query performance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_finance_entry_category_status ON finance_entry (category_id, status)');
        $this->addSql('CREATE INDEX IDX_finance_entry_split_entry_member ON finance_entry_split (entry_id, member_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_finance_entry_category_status ON finance_entry');
        $this->addSql('DROP INDEX IDX_finance_entry_split_entry_member ON finance_entry_split');
    }
}
