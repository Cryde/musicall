<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514124629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_resolved flag on forum_topic';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_topic ADD is_resolved TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_topic DROP is_resolved');
    }
}
