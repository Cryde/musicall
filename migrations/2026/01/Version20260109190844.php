<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260109190844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username_changed_datetime column to track username change cooldown';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD username_changed_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user DROP username_changed_datetime');
    }
}
