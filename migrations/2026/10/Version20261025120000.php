<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Images and voice notes in direct conversations (#1247): a file row owned by its sender and its
 * conversation, outside the Band Space files.
 */
final class Version20261025120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the message_file table for direct conversation media';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_file (id CHAR(36) NOT NULL, mime_type VARCHAR(191) NOT NULL, size INT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, storage_path VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, thread_id CHAR(36) NOT NULL, uploaded_by_id CHAR(36) NOT NULL, INDEX IDX_250AADC9E2904019 (thread_id), INDEX IDX_250AADC9A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE message_file ADD CONSTRAINT FK_250AADC9E2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_file ADD CONSTRAINT FK_250AADC9A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_file DROP FOREIGN KEY FK_250AADC9E2904019');
        $this->addSql('ALTER TABLE message_file DROP FOREIGN KEY FK_250AADC9A2B28FE8');
        $this->addSql('DROP TABLE message_file');
    }
}
