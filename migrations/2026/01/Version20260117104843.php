<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117104843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account RENAME INDEX idx_f24d8339a76ed395 TO IDX_99C85C60A76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account RENAME INDEX idx_99c85c60a76ed395 TO IDX_F24D8339A76ED395');
    }
}
