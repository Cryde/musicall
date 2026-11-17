<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A service is a draft or published (#1406). Every service stored today is a teacher profile already online, so it
 * stays published; the default only fills those rows.
 */
final class Version20261115190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the status of a service';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE service ADD status VARCHAR(20) DEFAULT 'published' NOT NULL");
        $this->addSql('ALTER TABLE service ALTER status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP status');
    }
}
