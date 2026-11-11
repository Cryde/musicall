<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chat message can answer another one (#1377). SET NULL rather than RESTRICT, unlike the rest of the
 * message domain, because MessageThreadRepository::deleteByBandSpace() deletes a channel's rows in one
 * statement, replies and answered messages together.
 */
final class Version20261111180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.reply_to_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD reply_to_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FFFDF7169 FOREIGN KEY (reply_to_id) REFERENCES message (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B6BD307FFFDF7169 ON message (reply_to_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FFFDF7169');
        $this->addSql('DROP INDEX IDX_B6BD307FFFDF7169 ON message');
        $this->addSql('ALTER TABLE message DROP reply_to_id');
    }
}
