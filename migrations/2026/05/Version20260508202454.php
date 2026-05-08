<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508202454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_file_share table for public share links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_file_share (id CHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, expiry_datetime DATETIME DEFAULT NULL, revocation_datetime DATETIME DEFAULT NULL, access_count INT NOT NULL, last_access_datetime DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, band_space_file_id CHAR(36) NOT NULL, created_by_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_BC2C494DB3BC57DA (token_hash), INDEX IDX_BC2C494DCDF230B2 (band_space_file_id), INDEX IDX_BC2C494DB03A8386 (created_by_id), INDEX idx_band_space_file_share_active (band_space_file_id, revocation_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file_share ADD CONSTRAINT FK_BC2C494DCDF230B2 FOREIGN KEY (band_space_file_id) REFERENCES band_space_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_file_share ADD CONSTRAINT FK_BC2C494DB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file_share DROP FOREIGN KEY FK_BC2C494DCDF230B2');
        $this->addSql('ALTER TABLE band_space_file_share DROP FOREIGN KEY FK_BC2C494DB03A8386');
        $this->addSql('DROP TABLE band_space_file_share');
    }
}
