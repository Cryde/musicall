<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016221317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forum category locale, every existing category reads fr (#1188)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_category ADD locale VARCHAR(10) DEFAULT \'fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_category DROP locale');
    }
}
