<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vote_cache_id column to comment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment ADD vote_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD UNIQUE INDEX UNIQ_9474526C83C5B397 (vote_cache_id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C83C5B397 FOREIGN KEY (vote_cache_id) REFERENCES vote_cache (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C83C5B397');
        $this->addSql('ALTER TABLE comment DROP INDEX UNIQ_9474526C83C5B397');
        $this->addSql('ALTER TABLE comment DROP vote_cache_id');
    }
}
