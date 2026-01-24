<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260124094558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY `FK_FEFDAB8EF91C231C`');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8EF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY FK_FEFDAB8EF91C231C');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT `FK_FEFDAB8EF91C231C` FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
    }
}
