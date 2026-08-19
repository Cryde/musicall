<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The unique index on (post_id, reporter_id) is what actually enforces one report per user per post.
 * The processor checks first so the user gets a readable 409 instead of a 500, but two concurrent
 * requests can both pass that check, and only the index stops the second insert.
 *
 * post and reporter cascade because a report has no meaning once either side is gone. resolved_by is
 * SET NULL instead: deleting a moderator account must not erase the report of a post that is still
 * online.
 */
final class Version20260819114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forum_post_report table for the forum moderation queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forum_post_report (id CHAR(36) NOT NULL, reason VARCHAR(500) NOT NULL, creation_datetime DATETIME NOT NULL, resolved_datetime DATETIME DEFAULT NULL, post_id CHAR(36) NOT NULL, reporter_id CHAR(36) NOT NULL, resolved_by_id CHAR(36) DEFAULT NULL, INDEX IDX_F23910434B89032C (post_id), INDEX IDX_F2391043E1CFE6F5 (reporter_id), INDEX IDX_F23910436713A32B (resolved_by_id), UNIQUE INDEX uniq_forum_post_report_post_reporter (post_id, reporter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE forum_post_report ADD CONSTRAINT FK_F23910434B89032C FOREIGN KEY (post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post_report ADD CONSTRAINT FK_F2391043E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post_report ADD CONSTRAINT FK_F23910436713A32B FOREIGN KEY (resolved_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post_report DROP FOREIGN KEY FK_F23910434B89032C');
        $this->addSql('ALTER TABLE forum_post_report DROP FOREIGN KEY FK_F2391043E1CFE6F5');
        $this->addSql('ALTER TABLE forum_post_report DROP FOREIGN KEY FK_F23910436713A32B');
        $this->addSql('DROP TABLE forum_post_report');
    }
}
