<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Me montrer en ligne » (#1040): whether the members of one's bands see one online in the chat.
 * On for everybody, the default a member turns off.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260926221101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the online presence preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ADD show_online_presence TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference DROP show_online_presence');
    }
}
