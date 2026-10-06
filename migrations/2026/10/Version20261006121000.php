<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A member's own consent before a tech rider prints their account email (#1119). Off for everyone,
 * existing members included: nobody was asked before.
 */
final class Version20261006121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the per member opt in for showing their email on tech riders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_membership ADD show_email_on_riders TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_membership DROP show_email_on_riders');
    }
}
