<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117144200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_email_log (id CHAR(36) NOT NULL, email_type VARCHAR(50) NOT NULL, reference_id VARCHAR(36) DEFAULT NULL, metadata JSON DEFAULT NULL, sent_datetime DATETIME NOT NULL, user_id CHAR(36) NOT NULL, INDEX IDX_61BC567A76ED395 (user_id), INDEX idx_user_email_type (user_id, email_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_email_log ADD CONSTRAINT FK_61BC567A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gallery_image CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_cover CHANGE image_name image_name VARCHAR(255) DEFAULT NULL, CHANGE image_size image_size INT DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_image CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_musician_profile_media CHANGE creation_datetime creation_datetime DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_profile_picture CHANGE image_name image_name VARCHAR(255) DEFAULT NULL, CHANGE image_size image_size INT DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_social_account CHANGE creation_datetime creation_datetime DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_email_log DROP FOREIGN KEY FK_61BC567A76ED395');
        $this->addSql('DROP TABLE user_email_log');
        $this->addSql('ALTER TABLE gallery_image CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE publication_cover CHANGE image_name image_name VARCHAR(255) NOT NULL, CHANGE image_size image_size INT NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE publication_image CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_musician_profile_media CHANGE creation_datetime creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_profile_picture CHANGE image_name image_name VARCHAR(255) NOT NULL, CHANGE image_size image_size INT NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_social_account CHANGE creation_datetime creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
