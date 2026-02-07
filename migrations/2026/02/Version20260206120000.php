<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vote_cache and vote tables for upvote/downvote system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vote_cache (id INT AUTO_INCREMENT NOT NULL, upvote_count INT NOT NULL DEFAULT 0, downvote_count INT NOT NULL DEFAULT 0, creation_datetime DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, vote_cache_id INT NOT NULL, user_id CHAR(36) DEFAULT NULL, identifier VARCHAR(128) NOT NULL, value SMALLINT NOT NULL, entity_type VARCHAR(50) DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL, creation_datetime DATETIME NOT NULL, INDEX IDX_5A108564CACB62F1 (vote_cache_id), INDEX IDX_5A108564A76ED395 (user_id), INDEX idx_vote_entity (entity_type, entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564CACB62F1 FOREIGN KEY (vote_cache_id) REFERENCES vote_cache (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication ADD vote_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779CACB62F1 FOREIGN KEY (vote_cache_id) REFERENCES vote_cache (id)');
        $this->addSql('CREATE INDEX IDX_AF3C6779CACB62F1 ON publication (vote_cache_id)');
        $this->addSql('DROP TABLE IF EXISTS publication_vote');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779CACB62F1');
        $this->addSql('DROP INDEX IDX_AF3C6779CACB62F1 ON publication');
        $this->addSql('ALTER TABLE publication DROP vote_cache_id');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564CACB62F1');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE vote_cache');
    }
}
