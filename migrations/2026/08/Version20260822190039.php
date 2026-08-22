<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822190039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the author of a Band Space note mandatory';
    }

    public function preUp(Schema $schema): void
    {
        $withoutAuthor = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM band_space_note WHERE created_by_id IS NULL'
        );

        $this->abortIf(
            $withoutAuthor > 0,
            sprintf(
                '%d note(s) still have no author. Fill created_by_id from the actor of each note\'s '
                . 'note_created row in band_space_activity, and assign one by hand where no such row exists.',
                $withoutAuthor,
            ),
        );
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note DROP FOREIGN KEY `FK_5FFFD959B03A8386`');
        $this->addSql('ALTER TABLE band_space_note CHANGE created_by_id created_by_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE band_space_note ADD CONSTRAINT FK_5FFFD959B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_note DROP FOREIGN KEY FK_5FFFD959B03A8386');
        $this->addSql('ALTER TABLE band_space_note CHANGE created_by_id created_by_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_note ADD CONSTRAINT `FK_5FFFD959B03A8386` FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }
}
