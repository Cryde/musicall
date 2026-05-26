<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525191334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notification table (per-recipient activity notifications, #713)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id CHAR(36) NOT NULL, type VARCHAR(40) NOT NULL, payload JSON NOT NULL, read_datetime DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, recipient_id CHAR(36) NOT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX idx_notification_recipient_read (recipient_id, read_datetime), INDEX idx_notification_recipient_created (recipient_id, creation_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('DROP TABLE notification');
    }
}
