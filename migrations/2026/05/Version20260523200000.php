<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_setlist + band_space_setlist_item tables (Set List module)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE band_space_setlist (
                id CHAR(36) NOT NULL,
                band_space_id CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                archive_datetime DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                creation_datetime DATETIME NOT NULL,
                update_datetime DATETIME DEFAULT NULL,
                INDEX idx_setlist_band_archive (band_space_id, archive_datetime),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE band_space_setlist ADD CONSTRAINT FK_BAND_SPACE_SETLIST_BAND FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE band_space_setlist_item (
                id CHAR(36) NOT NULL,
                setlist_id CHAR(36) NOT NULL,
                song_id CHAR(36) DEFAULT NULL,
                type VARCHAR(16) NOT NULL,
                label VARCHAR(255) DEFAULT NULL,
                duration_override INT DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                transition VARCHAR(50) DEFAULT NULL,
                position INT NOT NULL,
                INDEX idx_setlist_item_setlist_position (setlist_id, position),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE band_space_setlist_item ADD CONSTRAINT FK_BAND_SPACE_SETLIST_ITEM_SETLIST FOREIGN KEY (setlist_id) REFERENCES band_space_setlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_setlist_item ADD CONSTRAINT FK_BAND_SPACE_SETLIST_ITEM_SONG FOREIGN KEY (song_id) REFERENCES band_space_song (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_setlist_item DROP FOREIGN KEY FK_BAND_SPACE_SETLIST_ITEM_SONG');
        $this->addSql('ALTER TABLE band_space_setlist_item DROP FOREIGN KEY FK_BAND_SPACE_SETLIST_ITEM_SETLIST');
        $this->addSql('DROP TABLE band_space_setlist_item');
        $this->addSql('ALTER TABLE band_space_setlist DROP FOREIGN KEY FK_BAND_SPACE_SETLIST_BAND');
        $this->addSql('DROP TABLE band_space_setlist');
    }
}
