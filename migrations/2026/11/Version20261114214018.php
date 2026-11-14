<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Likes on wall post comments, and the stored count the « Plus appréciés » sort reads (#1331). Every comment
 * starts at 0, which is right: no comment could be liked before this table.
 */
final class Version20261114214018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_post_comment_like and profile_post_comment.like_count';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profile_post_comment_like (id CHAR(36) NOT NULL, creation_datetime DATETIME NOT NULL, comment_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, UNIQUE INDEX profile_post_comment_like_unique (comment_id, user_id), INDEX IDX_7F6DB00DF8697D13 (comment_id), INDEX IDX_7F6DB00DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE profile_post_comment_like ADD CONSTRAINT FK_7F6DB00DF8697D13 FOREIGN KEY (comment_id) REFERENCES profile_post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_comment_like ADD CONSTRAINT FK_7F6DB00DA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_comment ADD like_count INT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX profile_post_comment_liked_idx ON profile_post_comment (post_id, like_count, creation_datetime, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile_post_comment_like DROP FOREIGN KEY FK_7F6DB00DF8697D13');
        $this->addSql('ALTER TABLE profile_post_comment_like DROP FOREIGN KEY FK_7F6DB00DA76ED395');
        $this->addSql('DROP TABLE profile_post_comment_like');
        $this->addSql('DROP INDEX profile_post_comment_liked_idx ON profile_post_comment');
        $this->addSql('ALTER TABLE profile_post_comment DROP like_count');
    }
}
