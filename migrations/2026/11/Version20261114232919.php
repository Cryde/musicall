<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The switch for the up vote notification (#1081). On by default, like the other community notifications.
 */
final class Version20261114232919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_notification_preference.publication_vote';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ADD publication_vote TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference DROP publication_vote');
    }
}
