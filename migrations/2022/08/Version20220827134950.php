<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220827134950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forum (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', forum_category_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, position INT NOT NULL, topic_number INT NOT NULL, post_number INT NOT NULL, INDEX IDX_852BBECD14721E40 (forum_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_category (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', forum_source_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', title VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, position INT NOT NULL, INDEX IDX_21BF9426AB759837 (forum_source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_post (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', topic_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', creator_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, content LONGTEXT NOT NULL, INDEX IDX_996BCC5A1F55203D (topic_id), INDEX IDX_996BCC5A61220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_source (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', slug VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_topic (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', forum_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', last_post_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', title VARCHAR(255) NOT NULL, type INT NOT NULL, is_locked TINYINT(1) NOT NULL, creation_datetime DATETIME NOT NULL, INDEX IDX_853478CC29CCBAD0 (forum_id), INDEX IDX_853478CC2D053F64 (last_post_id), INDEX IDX_853478CCF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forum ADD CONSTRAINT FK_852BBECD14721E40 FOREIGN KEY (forum_category_id) REFERENCES forum_category (id)');
        $this->addSql('ALTER TABLE forum_category ADD CONSTRAINT FK_21BF9426AB759837 FOREIGN KEY (forum_source_id) REFERENCES forum_source (id)');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A1F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id)');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A61220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC29CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC2D053F64 FOREIGN KEY (last_post_id) REFERENCES forum_post (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC29CCBAD0');
        $this->addSql('ALTER TABLE forum DROP FOREIGN KEY FK_852BBECD14721E40');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC2D053F64');
        $this->addSql('ALTER TABLE forum_category DROP FOREIGN KEY FK_21BF9426AB759837');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5A1F55203D');
        $this->addSql('DROP TABLE forum');
        $this->addSql('DROP TABLE forum_category');
        $this->addSql('DROP TABLE forum_post');
        $this->addSql('DROP TABLE forum_source');
        $this->addSql('DROP TABLE forum_topic');
    }
}
