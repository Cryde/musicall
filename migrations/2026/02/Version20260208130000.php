<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vote_cache_id column to forum_post table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post ADD vote_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forum_post ADD UNIQUE INDEX UNIQ_996BCC5A83C5B397 (vote_cache_id)');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A83C5B397 FOREIGN KEY (vote_cache_id) REFERENCES vote_cache (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5A83C5B397');
        $this->addSql('ALTER TABLE forum_post DROP INDEX UNIQ_996BCC5A83C5B397');
        $this->addSql('ALTER TABLE forum_post DROP vote_cache_id');
    }
}
