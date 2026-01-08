<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108073736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename created_at to creation_datetime in user_social_account table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account CHANGE created_at creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account CHANGE creation_datetime created_at DATETIME NOT NULL');
    }
}
