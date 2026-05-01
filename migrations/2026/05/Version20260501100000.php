<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make task.created_by_id nullable and switch FK to ON DELETE SET NULL so deleting a user does not wipe band space tasks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task CHANGE created_by_id created_by_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task CHANGE created_by_id created_by_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }
}
