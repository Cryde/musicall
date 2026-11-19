<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1419: the availability reminder push gets its own switch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ADD push_band_agenda_reminder TINYINT DEFAULT 1 NOT NULL');
        // The agenda switch covered reminders until now: whoever turned it off keeps them off.
        $this->addSql('UPDATE user_notification_preference SET push_band_agenda_reminder = push_band_agenda');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference DROP push_band_agenda_reminder');
    }
}
