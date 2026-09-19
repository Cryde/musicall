<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919135208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Emoji reactions on a Band Space chat message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_reaction (
            id CHAR(36) NOT NULL,
            message_id CHAR(36) NOT NULL,
            user_id CHAR(36) NOT NULL,
            emoji VARCHAR(20) NOT NULL,
            UNIQUE INDEX message_reaction_unique (message_id, user_id, emoji),
            INDEX idx_message_reaction_user (user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6537A1329 FOREIGN KEY (message_id) REFERENCES message (id)');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_ADF1C3E6537A1329');
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_ADF1C3E6A76ED395');
        $this->addSql('DROP TABLE message_reaction');
    }
}
