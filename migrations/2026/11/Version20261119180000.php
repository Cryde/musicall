<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1418: an email switch for the availability reminder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ADD band_agenda_reminder TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference DROP band_agenda_reminder');
    }
}
