<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A trial lesson request says so on its conversation card (#1322).
 */
final class Version20261110100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message_contact_origin.lesson_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_contact_origin ADD lesson_request VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_contact_origin DROP lesson_request');
    }
}
