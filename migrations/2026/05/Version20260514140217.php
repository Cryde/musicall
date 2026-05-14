<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514140217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forum_image table for inline images in forum messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forum_image (id INT AUTO_INCREMENT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, image_size INT DEFAULT NULL, creation_datetime DATETIME NOT NULL, creator_id CHAR(36) NOT NULL, INDEX IDX_DD49A28861220EA6 (creator_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE forum_image ADD CONSTRAINT FK_DD49A28861220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_image DROP FOREIGN KEY FK_DD49A28861220EA6');
        $this->addSql('DROP TABLE forum_image');
    }
}
