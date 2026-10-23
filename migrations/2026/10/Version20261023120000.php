<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Vu » in direct conversations (#1243): a seen position that only moves forward, started where each
 * read position is today, and the preference to hide it, on for everybody.
 */
final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the seen position and the read receipt preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta ADD last_seen_datetime DATETIME DEFAULT NULL');
        $this->addSql('UPDATE message_thread_meta SET last_seen_datetime = last_read_datetime');
        $this->addSql('ALTER TABLE user_notification_preference ADD show_read_receipts TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta DROP last_seen_datetime');
        $this->addSql('ALTER TABLE user_notification_preference DROP show_read_receipts');
    }
}
