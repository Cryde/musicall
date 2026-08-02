<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Purely additive, so the generated SQL is kept as is.
 *
 * CASCADE on both sides of the join table: a row there records that a member plays an instrument,
 * and it means nothing once either end is gone.
 */
final class Version20260802195245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stage name and instruments to a band space membership';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_membership_instrument (membership_id CHAR(36) NOT NULL, instrument_id CHAR(36) NOT NULL, INDEX IDX_280ECF111FB354CD (membership_id), INDEX IDX_280ECF11CF11D9C (instrument_id), PRIMARY KEY (membership_id, instrument_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_membership_instrument ADD CONSTRAINT FK_280ECF111FB354CD FOREIGN KEY (membership_id) REFERENCES band_space_membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_membership_instrument ADD CONSTRAINT FK_280ECF11CF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_membership ADD stage_name VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_membership_instrument DROP FOREIGN KEY FK_280ECF111FB354CD');
        $this->addSql('ALTER TABLE band_space_membership_instrument DROP FOREIGN KEY FK_280ECF11CF11D9C');
        $this->addSql('DROP TABLE band_space_membership_instrument');
        $this->addSql('ALTER TABLE band_space_membership DROP stage_name');
    }
}
