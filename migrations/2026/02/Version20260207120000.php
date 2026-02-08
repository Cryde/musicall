<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletion_datetime column to fos_user for account deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD deletion_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user DROP deletion_datetime');
    }
}
