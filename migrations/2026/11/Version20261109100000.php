<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The profile wall (#1316): comments, replies and likes on posts.
 */
final class Version20261109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_post_comment and profile_post_like';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profile_post_like (id CHAR(36) NOT NULL, creation_datetime DATETIME NOT NULL, post_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, INDEX profile_post_like_post_idx (post_id, creation_datetime), UNIQUE INDEX profile_post_like_unique (post_id, user_id), INDEX IDX_D9A9CEC34B89032C (post_id), INDEX IDX_D9A9CEC3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE profile_post_comment (id CHAR(36) NOT NULL, content LONGTEXT NOT NULL, creation_datetime DATETIME NOT NULL, post_id CHAR(36) NOT NULL, author_id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, INDEX profile_post_comment_post_idx (post_id, creation_datetime), INDEX IDX_1E34A4224B89032C (post_id), INDEX IDX_1E34A422F675F31B (author_id), INDEX IDX_1E34A422727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE profile_post_like ADD CONSTRAINT FK_D9A9CEC34B89032C FOREIGN KEY (post_id) REFERENCES profile_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_like ADD CONSTRAINT FK_D9A9CEC3A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_comment ADD CONSTRAINT FK_1E34A4224B89032C FOREIGN KEY (post_id) REFERENCES profile_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_comment ADD CONSTRAINT FK_1E34A422F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_post_comment ADD CONSTRAINT FK_1E34A422727ACA70 FOREIGN KEY (parent_id) REFERENCES profile_post_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile_post_comment DROP FOREIGN KEY FK_1E34A422727ACA70');
        $this->addSql('ALTER TABLE profile_post_comment DROP FOREIGN KEY FK_1E34A422F675F31B');
        $this->addSql('ALTER TABLE profile_post_comment DROP FOREIGN KEY FK_1E34A4224B89032C');
        $this->addSql('ALTER TABLE profile_post_like DROP FOREIGN KEY FK_D9A9CEC3A76ED395');
        $this->addSql('ALTER TABLE profile_post_like DROP FOREIGN KEY FK_D9A9CEC34B89032C');
        $this->addSql('DROP TABLE profile_post_comment');
        $this->addSql('DROP TABLE profile_post_like');
    }
}
