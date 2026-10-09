<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task text version: refuse a title or description save made from a stale copy (#1157)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD text_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP text_version');
    }
}
