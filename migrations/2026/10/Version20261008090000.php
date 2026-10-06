<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Users blocking other users (#1117).
 */
final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user blocks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_block (id CHAR(36) NOT NULL, creation_datetime DATETIME NOT NULL, blocker_id CHAR(36) NOT NULL, blocked_id CHAR(36) NOT NULL, UNIQUE INDEX user_block_pair (blocker_id, blocked_id), INDEX IDX_61D96C7A548D5975 (blocker_id), INDEX IDX_61D96C7A21FF5136 (blocked_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_block ADD CONSTRAINT FK_61D96C7A548D5975 FOREIGN KEY (blocker_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_block ADD CONSTRAINT FK_61D96C7A21FF5136 FOREIGN KEY (blocked_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_block');
    }
}
