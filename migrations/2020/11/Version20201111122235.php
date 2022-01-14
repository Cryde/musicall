<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201111122235 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gallery ADD view_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_472B783AF91C231C ON gallery (view_cache_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AF91C231C');
        $this->addSql('DROP INDEX UNIQ_472B783AF91C231C ON gallery');
        $this->addSql('ALTER TABLE gallery DROP view_cache_id');
    }
}
