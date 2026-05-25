<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525083918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_notification_sent to message_thread_meta for one-email-per-unread-streak (#533)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta ADD pending_notification_sent TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta DROP pending_notification_sent');
    }
}
