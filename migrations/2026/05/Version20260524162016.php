<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260524162016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT indexes on forum_topic.title and forum_post.content for forum search (#681)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE FULLTEXT INDEX idx_forum_post_content_ft ON forum_post (content)');
        $this->addSql('CREATE FULLTEXT INDEX idx_forum_topic_title_ft ON forum_topic (title)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_forum_post_content_ft ON forum_post');
        $this->addSql('DROP INDEX idx_forum_topic_title_ft ON forum_topic');
    }
}
