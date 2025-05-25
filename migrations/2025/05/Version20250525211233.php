<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250525211233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wiki_artist_cover DROP FOREIGN KEY FK_6F99C115B7970CF8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE artist DROP FOREIGN KEY FK_1599687922726E9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE artist_social DROP FOREIGN KEY FK_8363EDF9B7970CF8
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE wiki_artist_cover
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE artist
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE artist_social
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_instrument CHANGE id id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_style CHANGE id id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum CHANGE id id CHAR(36) NOT NULL, CHANGE forum_category_id forum_category_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_category CHANGE id id CHAR(36) NOT NULL, CHANGE forum_source_id forum_source_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_post CHANGE id id CHAR(36) NOT NULL, CHANGE topic_id topic_id CHAR(36) NOT NULL, CHANGE creator_id creator_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_source CHANGE id id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_topic CHANGE id id CHAR(36) NOT NULL, CHANGE forum_id forum_id CHAR(36) NOT NULL, CHANGE last_post_id last_post_id CHAR(36) DEFAULT NULL, CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fos_user CHANGE id id CHAR(36) NOT NULL, CHANGE roles roles JSON NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE gallery CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message CHANGE id id CHAR(36) NOT NULL, CHANGE thread_id thread_id CHAR(36) NOT NULL, CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_participant CHANGE id id CHAR(36) NOT NULL, CHANGE thread_id thread_id CHAR(36) NOT NULL, CHANGE participant_id participant_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_thread CHANGE id id CHAR(36) NOT NULL, CHANGE last_message_id last_message_id CHAR(36) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_thread_meta CHANGE id id CHAR(36) NOT NULL, CHANGE thread_id thread_id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE musician_announce CHANGE id id CHAR(36) NOT NULL, CHANGE instrument_id instrument_id CHAR(36) NOT NULL, CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE musician_announce_style CHANGE musician_announce_id musician_announce_id CHAR(36) NOT NULL, CHANGE style_id style_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE publication CHANGE author_id author_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE publication_featured CHANGE options options JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_profile_picture CHANGE user_id user_id CHAR(36) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE view CHANGE user_id user_id CHAR(36) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE wiki_artist_cover (id INT AUTO_INCREMENT NOT NULL, artist_id INT DEFAULT NULL, image_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F99C115B7970CF8 (artist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE artist (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, biography LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, creation_datetime DATETIME NOT NULL, members LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, label_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, slug VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, cover_id INT DEFAULT NULL, country_code VARCHAR(3) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_15996875E237E06 (name), UNIQUE INDEX UNIQ_1599687989D9B62 (slug), UNIQUE INDEX UNIQ_1599687922726E9 (cover_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE artist_social (id INT AUTO_INCREMENT NOT NULL, artist_id INT NOT NULL, type SMALLINT NOT NULL, url VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, creation_datetime DATETIME NOT NULL, INDEX IDX_8363EDF9B7970CF8 (artist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wiki_artist_cover ADD CONSTRAINT FK_6F99C115B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE artist ADD CONSTRAINT FK_1599687922726E9 FOREIGN KEY (cover_id) REFERENCES wiki_artist_cover (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE artist_social ADD CONSTRAINT FK_8363EDF9B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE thread_id thread_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_instrument CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_thread CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE last_message_id last_message_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_category CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE forum_source_id forum_source_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_source CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fos_user CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE roles roles JSON NOT NULL COMMENT '(DC2Type:json)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE musician_announce CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE instrument_id instrument_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_profile_picture CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_participant CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE thread_id thread_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE participant_id participant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE publication CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_topic CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE forum_id forum_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE last_post_id last_post_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE view CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum_post CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE topic_id topic_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE creator_id creator_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE gallery CHANGE author_id author_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_thread_meta CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE thread_id thread_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_style CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE forum CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE forum_category_id forum_category_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE musician_announce_style CHANGE musician_announce_id musician_announce_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE style_id style_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
    }
}
