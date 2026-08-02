<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renames band_space_tech_rider_section to band_space_tech_rider_item and gives it the two
 * columns that make it a typed, composable block: `type` and `is_included`.
 *
 * Written by hand rather than taken from doctrine:migrations:diff, which proposed dropping
 * the old table and creating the new one. That would have discarded every row. A rename
 * keeps them, and the `type` default backfills the existing ones as text items, which is
 * what they are.
 */
final class Version20260802140733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename tech rider sections to typed, composable items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_section RENAME TO band_space_tech_rider_item');

        // The foreign key holds the index open, so it goes first.
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP FOREIGN KEY FK_69559A219FF2F0D');
        $this->addSql('DROP INDEX IDX_69559A219FF2F0D ON band_space_tech_rider_item');
        $this->addSql('DROP INDEX idx_rider_section_rider_position ON band_space_tech_rider_item');

        // Added with a default so existing rows backfill, then the default is dropped: the
        // entity declares none, and leaving one behind would show as schema drift forever.
        $this->addSql("ALTER TABLE band_space_tech_rider_item ADD type VARCHAR(20) DEFAULT 'text' NOT NULL");
        $this->addSql('ALTER TABLE band_space_tech_rider_item ALTER COLUMN type DROP DEFAULT');
        $this->addSql('ALTER TABLE band_space_tech_rider_item ADD is_included TINYINT(1) DEFAULT 1 NOT NULL');

        $this->addSql('CREATE INDEX IDX_E5B0A289FF2F0D ON band_space_tech_rider_item (tech_rider_id)');
        $this->addSql('CREATE INDEX idx_rider_item_rider_position ON band_space_tech_rider_item (tech_rider_id, position)');
        $this->addSql('ALTER TABLE band_space_tech_rider_item ADD CONSTRAINT FK_E5B0A289FF2F0D FOREIGN KEY (tech_rider_id) REFERENCES band_space_tech_rider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP FOREIGN KEY FK_E5B0A289FF2F0D');
        $this->addSql('DROP INDEX IDX_E5B0A289FF2F0D ON band_space_tech_rider_item');
        $this->addSql('DROP INDEX idx_rider_item_rider_position ON band_space_tech_rider_item');

        // Non-text items cannot exist in the old shape, so they go rather than being silently
        // reinterpreted as text blocks with no content.
        $this->addSql("DELETE FROM band_space_tech_rider_item WHERE type <> 'text'");
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP type');
        $this->addSql('ALTER TABLE band_space_tech_rider_item DROP is_included');

        $this->addSql('ALTER TABLE band_space_tech_rider_item RENAME TO band_space_tech_rider_section');
        $this->addSql('CREATE INDEX IDX_69559A219FF2F0D ON band_space_tech_rider_section (tech_rider_id)');
        $this->addSql('CREATE INDEX idx_rider_section_rider_position ON band_space_tech_rider_section (tech_rider_id, position)');
        $this->addSql('ALTER TABLE band_space_tech_rider_section ADD CONSTRAINT FK_69559A219FF2F0D FOREIGN KEY (tech_rider_id) REFERENCES band_space_tech_rider (id) ON DELETE CASCADE');
    }
}
