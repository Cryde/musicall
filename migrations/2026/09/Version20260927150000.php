<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The preference for the emails about new announces answering a member's (#1082), on by default
 * like the other activity emails.
 */
final class Version20260927150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the announce match notification preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ADD announce_match TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference DROP announce_match');
    }
}
