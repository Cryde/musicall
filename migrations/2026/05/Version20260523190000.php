<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_song table (Set List module - shared song catalog per band)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE band_space_song (
                id CHAR(36) NOT NULL,
                band_space_id CHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                tempo INT DEFAULT NULL,
                tonality VARCHAR(16) DEFAULT NULL,
                reference_duration INT DEFAULT NULL,
                notes LONGTEXT DEFAULT NULL,
                archive_datetime DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                creation_datetime DATETIME NOT NULL,
                update_datetime DATETIME DEFAULT NULL,
                INDEX idx_song_band_archive (band_space_id, archive_datetime),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE band_space_song ADD CONSTRAINT FK_BAND_SPACE_SONG_BAND FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_song DROP FOREIGN KEY FK_BAND_SPACE_SONG_BAND');
        $this->addSql('DROP TABLE band_space_song');
    }
}
