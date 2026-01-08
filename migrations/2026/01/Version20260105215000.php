<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105215000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_social_account (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(50) NOT NULL, provider_id VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id CHAR(36) NOT NULL, INDEX IDX_F24D8339A76ED395 (user_id), UNIQUE INDEX user_social_account_provider_unique (provider, provider_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_social_account ADD CONSTRAINT FK_F24D8339A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE fos_user CHANGE password password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_social_account DROP FOREIGN KEY FK_F24D8339A76ED395');
        $this->addSql('DROP TABLE user_social_account');
        $this->addSql('ALTER TABLE fos_user CHANGE password password VARCHAR(255) NOT NULL');
    }
}
