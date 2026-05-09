<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move single (attached_source_type, attached_source_id) columns on band_space_file
 * to a join table so a file can have multiple attachments.
 */
final class Version20260509142721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate single source attachment columns to band_space_file_attachment join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_file_attachment (id CHAR(36) NOT NULL, source_type VARCHAR(20) NOT NULL, source_id CHAR(36) NOT NULL, attached_datetime DATETIME NOT NULL, band_space_file_id CHAR(36) NOT NULL, attached_by_id CHAR(36) DEFAULT NULL, INDEX IDX_A6E57146CDF230B2 (band_space_file_id), INDEX IDX_A6E57146A7B6C524 (attached_by_id), INDEX idx_attachment_source (source_type, source_id), UNIQUE INDEX uniq_attachment_file_source (band_space_file_id, source_type, source_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file_attachment ADD CONSTRAINT FK_A6E57146CDF230B2 FOREIGN KEY (band_space_file_id) REFERENCES band_space_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_file_attachment ADD CONSTRAINT FK_A6E57146A7B6C524 FOREIGN KEY (attached_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');

        // Backfill: convert each existing single-source row into an attachment row.
        $this->addSql(<<<'SQL'
            INSERT INTO band_space_file_attachment (id, band_space_file_id, source_type, source_id, attached_datetime, attached_by_id)
            SELECT UUID(), id, attached_source_type, attached_source_id, COALESCE(update_datetime, creation_datetime), created_by_id
            FROM band_space_file
            WHERE attached_source_type IS NOT NULL AND attached_source_id IS NOT NULL
        SQL);

        $this->addSql('DROP INDEX idx_band_space_file_attached_source ON band_space_file');
        $this->addSql('ALTER TABLE band_space_file DROP attached_source_type, DROP attached_source_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file ADD attached_source_type VARCHAR(20) DEFAULT NULL, ADD attached_source_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_band_space_file_attached_source ON band_space_file (attached_source_type, attached_source_id)');

        // Roll back the first attachment of each file (single-source legacy shape).
        $this->addSql(<<<'SQL'
            UPDATE band_space_file f
            INNER JOIN (
                SELECT band_space_file_id, MIN(attached_datetime) AS first_attached
                FROM band_space_file_attachment
                GROUP BY band_space_file_id
            ) earliest ON earliest.band_space_file_id = f.id
            INNER JOIN band_space_file_attachment a
                ON a.band_space_file_id = earliest.band_space_file_id AND a.attached_datetime = earliest.first_attached
            SET f.attached_source_type = a.source_type, f.attached_source_id = a.source_id
        SQL);

        $this->addSql('ALTER TABLE band_space_file_attachment DROP FOREIGN KEY FK_A6E57146CDF230B2');
        $this->addSql('ALTER TABLE band_space_file_attachment DROP FOREIGN KEY FK_A6E57146A7B6C524');
        $this->addSql('DROP TABLE band_space_file_attachment');
    }
}
