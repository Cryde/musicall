<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A new table only, so the generated SQL is kept as is.
 *
 * ON DELETE CASCADE: a patch row has no meaning without the item it belongs to, unlike the
 * file reference added in Version20260802160330, which is SET NULL because the file is a
 * separate thing the band owns.
 */
final class Version20260802170150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the rows that back a PatchList rider item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_tech_rider_patch_row (id CHAR(36) NOT NULL, direction VARCHAR(10) NOT NULL, channel SMALLINT NOT NULL, name VARCHAR(120) DEFAULT NULL, microphone VARCHAR(120) DEFAULT NULL, routing VARCHAR(180) DEFAULT NULL, colour VARCHAR(10) DEFAULT NULL, position INT NOT NULL, tech_rider_item_id CHAR(36) NOT NULL, INDEX IDX_2882AEBDABF1CA3A (tech_rider_item_id), INDEX idx_patch_row_item_dir_pos (tech_rider_item_id, direction, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_tech_rider_patch_row ADD CONSTRAINT FK_2882AEBDABF1CA3A FOREIGN KEY (tech_rider_item_id) REFERENCES band_space_tech_rider_item (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_patch_row DROP FOREIGN KEY FK_2882AEBDABF1CA3A');
        $this->addSql('DROP TABLE band_space_tech_rider_patch_row');
    }
}
