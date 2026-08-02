<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Purely additive, so the generated SQL is kept as is.
 *
 * ON DELETE SET NULL, not cascade: deleting the file must not silently delete a page of the
 * rider. The item survives with no file and says so.
 */
final class Version20260802160330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the file reference that backs a Document rider item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_item ADD file_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_tech_rider_item ADD CONSTRAINT FK_E5B0A2893CB796C FOREIGN KEY (file_id) REFERENCES band_space_file (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E5B0A2893CB796C ON band_space_tech_rider_item (file_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP FOREIGN KEY FK_E5B0A2893CB796C');
        $this->addSql('DROP INDEX IDX_E5B0A2893CB796C ON band_space_tech_rider_item');
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP file_id');
    }
}
