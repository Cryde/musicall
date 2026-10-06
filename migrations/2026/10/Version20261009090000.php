<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Push notification categories (#1110), every one on by default.
 */
final class Version20261009090000 extends AbstractMigration
{
    private const array COLUMNS = [
        'push_message_received',
        'push_publication_comment',
        'push_forum_reply',
        'push_moderation',
        'push_band_chat',
        'push_band_mention',
        'push_band_tasks',
        'push_band_agenda',
        'push_band_finance',
        'push_band_membership',
    ];

    public function getDescription(): string
    {
        return 'Add the push notification categories to the notification preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ' . implode(', ', array_map(
            static fn (string $column): string => sprintf('ADD %s TINYINT DEFAULT 1 NOT NULL', $column),
            self::COLUMNS,
        )));
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification_preference ' . implode(', ', array_map(
            static fn (string $column): string => sprintf('DROP %s', $column),
            self::COLUMNS,
        )));
    }
}
