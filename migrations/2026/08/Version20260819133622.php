<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Purely additive, so the generated SQL is kept as is.
 *
 * Nullable, and ON DELETE SET NULL rather than CASCADE, for the two cases the column has to survive:
 * a note written before this migration has nobody recorded, and a member closing their account must
 * not take the band's pages with them. Both read back as NULL, and NoteOwnerChecker treats that as
 * "admins only".
 *
 * This migration writes no data of its own. A one-shot command, deleted once it had run, filled what
 * the activity feed answers exactly, from the note_created row carrying both the note id and its
 * author, and left the rest NULL: notes older than that feed have nothing to read, and guessing from
 * any other activity type would have handed delete rights to whoever it named.
 */
final class Version20260819133622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the author of a Band Space note';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note ADD created_by_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_note ADD CONSTRAINT FK_5FFFD959B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5FFFD959B03A8386 ON band_space_note (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note DROP FOREIGN KEY FK_5FFFD959B03A8386');
        $this->addSql('DROP INDEX IDX_5FFFD959B03A8386 ON band_space_note');
        $this->addSql('ALTER TABLE band_space_note DROP created_by_id');
    }
}
