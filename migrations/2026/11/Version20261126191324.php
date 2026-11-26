<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126191324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1451: folders inside a song\'s files, and the folder each song attachment sits in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_song_file_folder (id CHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, song_id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, created_by_id CHAR(36) NOT NULL, INDEX idx_song_file_folder_parent (song_id, parent_id), UNIQUE INDEX unique_song_file_folder_sibling_name (song_id, parent_id, name), INDEX IDX_A8D05DE7A0BDB2F3 (song_id), INDEX IDX_A8D05DE7727ACA70 (parent_id), INDEX IDX_A8D05DE7B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_song_file_folder ADD CONSTRAINT FK_A8D05DE7A0BDB2F3 FOREIGN KEY (song_id) REFERENCES band_space_song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_song_file_folder ADD CONSTRAINT FK_A8D05DE7727ACA70 FOREIGN KEY (parent_id) REFERENCES band_space_song_file_folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_song_file_folder ADD CONSTRAINT FK_A8D05DE7B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE band_space_file_attachment ADD song_folder_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_file_attachment ADD CONSTRAINT FK_A6E571466E80ACB6 FOREIGN KEY (song_folder_id) REFERENCES band_space_song_file_folder (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A6E571466E80ACB6 ON band_space_file_attachment (song_folder_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file_attachment DROP FOREIGN KEY FK_A6E571466E80ACB6');
        $this->addSql('DROP INDEX IDX_A6E571466E80ACB6 ON band_space_file_attachment');
        $this->addSql('ALTER TABLE band_space_file_attachment DROP song_folder_id');
        $this->addSql('ALTER TABLE band_space_song_file_folder DROP FOREIGN KEY FK_A8D05DE7A0BDB2F3');
        $this->addSql('ALTER TABLE band_space_song_file_folder DROP FOREIGN KEY FK_A8D05DE7727ACA70');
        $this->addSql('ALTER TABLE band_space_song_file_folder DROP FOREIGN KEY FK_A8D05DE7B03A8386');
        $this->addSql('DROP TABLE band_space_song_file_folder');
    }
}
