<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112083058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_musician_profile_media table for media showcase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_musician_profile_media (id CHAR(36) NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, embed_id VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, thumbnail_image_name VARCHAR(255) DEFAULT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', musician_profile_id CHAR(36) NOT NULL, INDEX IDX_1B6CE5F7DFD1907F (musician_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_musician_profile_media ADD CONSTRAINT FK_1B6CE5F7DFD1907F FOREIGN KEY (musician_profile_id) REFERENCES user_musician_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_musician_profile_media DROP FOREIGN KEY FK_1B6CE5F7DFD1907F');
        $this->addSql('DROP TABLE user_musician_profile_media');
    }
}
