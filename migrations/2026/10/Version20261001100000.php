<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Finance amounts are cents, and a signed INT stops at 21 474 836,47 €. The API now caps them at
 * 100 000 000 € (#1045), which needs BIGINT. Nullability is unchanged.
 *
 * down() fails once an amount above the INT range is stored, which is the point of up().
 */
final class Version20261001100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store finance amounts as BIGINT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry CHANGE amount amount BIGINT DEFAULT NULL, CHANGE amount_min amount_min BIGINT DEFAULT NULL, CHANGE amount_max amount_max BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE finance_recurrence CHANGE amount amount BIGINT NOT NULL');
        $this->addSql('ALTER TABLE finance_entry_split CHANGE amount amount BIGINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_entry CHANGE amount amount INT DEFAULT NULL, CHANGE amount_min amount_min INT DEFAULT NULL, CHANGE amount_max amount_max INT DEFAULT NULL');
        $this->addSql('ALTER TABLE finance_recurrence CHANGE amount amount INT NOT NULL');
        $this->addSql('ALTER TABLE finance_entry_split CHANGE amount amount INT NOT NULL');
    }
}
