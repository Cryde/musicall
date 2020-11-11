<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201111101619 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE view (id INT AUTO_INCREMENT NOT NULL, user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', view_cache_id INT NOT NULL, creation_datetime DATETIME NOT NULL, identifier VARCHAR(255) NOT NULL, INDEX IDX_FEFDAB8EA76ED395 (user_id), INDEX IDX_FEFDAB8EF91C231C (view_cache_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view_cache (id INT AUTO_INCREMENT NOT NULL, count INT NOT NULL, creation_datetime DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8EA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8EF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE publication ADD view_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF3C6779F91C231C ON publication (view_cache_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779F91C231C');
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY FK_FEFDAB8EF91C231C');
        $this->addSql('DROP TABLE view');
        $this->addSql('DROP TABLE view_cache');
        $this->addSql('DROP INDEX UNIQ_AF3C6779F91C231C ON publication');
        $this->addSql('ALTER TABLE publication DROP view_cache_id');
    }
}
