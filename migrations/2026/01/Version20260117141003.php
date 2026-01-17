<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117141003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_notification_preference (id CHAR(36) NOT NULL, site_news TINYINT NOT NULL, weekly_recap TINYINT NOT NULL, message_received TINYINT NOT NULL, publication_comment TINYINT NOT NULL, forum_reply TINYINT NOT NULL, marketing TINYINT NOT NULL, activity_reminder TINYINT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, user_id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_1FCABC20A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_notification_preference ADD CONSTRAINT FK_1FCABC20A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_notification_preference DROP FOREIGN KEY FK_1FCABC20A76ED395');
        $this->addSql('DROP TABLE user_notification_preference');
    }
}
