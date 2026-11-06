<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The profile wall (#1316): posts and their photos.
 */
final class Version20261108100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_post and profile_post_image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profile_post (id CHAR(36) NOT NULL, content LONGTEXT DEFAULT NULL, media_platform VARCHAR(20) DEFAULT NULL, media_url VARCHAR(500) DEFAULT NULL, media_embed_id VARCHAR(255) DEFAULT NULL, media_title VARCHAR(255) DEFAULT NULL, is_pinned TINYINT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, author_id CHAR(36) NOT NULL, INDEX profile_post_wall_idx (author_id, creation_datetime, id), INDEX IDX_EC42E319F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE profile_post_image (id CHAR(36) NOT NULL, position SMALLINT NOT NULL, mime_type VARCHAR(191) NOT NULL, size INT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, storage_path VARCHAR(255) DEFAULT NULL, post_id CHAR(36) NOT NULL, INDEX IDX_954DBFED4B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE profile_post ADD CONSTRAINT FK_EC42E319F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_image ADD CONSTRAINT FK_954DBFED4B89032C FOREIGN KEY (post_id) REFERENCES profile_post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile_post_image DROP FOREIGN KEY FK_954DBFED4B89032C');
        $this->addSql('ALTER TABLE profile_post DROP FOREIGN KEY FK_EC42E319F675F31B');
        $this->addSql('DROP TABLE profile_post_image');
        $this->addSql('DROP TABLE profile_post');
    }
}
