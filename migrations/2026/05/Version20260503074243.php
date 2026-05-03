<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503074243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add update_datetime to task_comment to track edited comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_comment ADD update_datetime DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_comment DROP update_datetime');
    }
}
