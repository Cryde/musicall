<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110091436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_profile (id CHAR(36) NOT NULL, bio LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, is_public TINYINT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, user_id CHAR(36) NOT NULL, cover_picture_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D95AB405A76ED395 (user_id), UNIQUE INDEX UNIQ_D95AB405C50D86A0 (cover_picture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_profile_cover_picture (id INT AUTO_INCREMENT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, image_size INT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, profile_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_F031269FCCFA12B8 (profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_social_link (id INT AUTO_INCREMENT NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, creation_datetime DATETIME NOT NULL, profile_id CHAR(36) NOT NULL, INDEX IDX_D0A68300CCFA12B8 (profile_id), UNIQUE INDEX unique_profile_platform (profile_id, platform), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT FK_D95AB405A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT FK_D95AB405C50D86A0 FOREIGN KEY (cover_picture_id) REFERENCES user_profile_cover_picture (id)');
        $this->addSql('ALTER TABLE user_profile_cover_picture ADD CONSTRAINT FK_F031269FCCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id)');
        $this->addSql('ALTER TABLE user_social_link ADD CONSTRAINT FK_D0A68300CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY FK_D95AB405A76ED395');
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY FK_D95AB405C50D86A0');
        $this->addSql('ALTER TABLE user_profile_cover_picture DROP FOREIGN KEY FK_F031269FCCFA12B8');
        $this->addSql('ALTER TABLE user_social_link DROP FOREIGN KEY FK_D0A68300CCFA12B8');
        $this->addSql('DROP TABLE user_profile');
        $this->addSql('DROP TABLE user_profile_cover_picture');
        $this->addSql('DROP TABLE user_social_link');
    }
}
