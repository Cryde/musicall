<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230128083152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE artist (id INT AUTO_INCREMENT NOT NULL, cover_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, biography LONGTEXT DEFAULT NULL, creation_datetime DATETIME NOT NULL, members LONGTEXT DEFAULT NULL, label_name VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, country_code VARCHAR(3) DEFAULT NULL, UNIQUE INDEX UNIQ_15996875E237E06 (name), UNIQUE INDEX UNIQ_1599687989D9B62 (slug), UNIQUE INDEX UNIQ_1599687922726E9 (cover_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE artist_social (id INT AUTO_INCREMENT NOT NULL, artist_id INT NOT NULL, type SMALLINT NOT NULL, url VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, INDEX IDX_8363EDF9B7970CF8 (artist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE attribute_instrument (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, musician_name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, UNIQUE INDEX UNIQ_38D96EB85E237E06 (name), UNIQUE INDEX UNIQ_38D96EB8C021C784 (musician_name), UNIQUE INDEX UNIQ_38D96EB8989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE attribute_style (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, UNIQUE INDEX UNIQ_D0855BDC5E237E06 (name), UNIQUE INDEX UNIQ_D0855BDC989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, thread_id INT NOT NULL, author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, content LONGTEXT NOT NULL, INDEX IDX_9474526CE2904019 (thread_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment_thread (id INT AUTO_INCREMENT NOT NULL, comment_number INT NOT NULL, is_active TINYINT(1) NOT NULL, creation_datetime DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', forum_category_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, position INT NOT NULL, topic_number INT NOT NULL, post_number INT NOT NULL, UNIQUE INDEX UNIQ_852BBECD989D9B62 (slug), INDEX IDX_852BBECD14721E40 (forum_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_category (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', forum_source_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, position INT NOT NULL, INDEX IDX_21BF9426AB759837 (forum_source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_post (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', topic_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creator_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, content LONGTEXT NOT NULL, INDEX IDX_996BCC5A1F55203D (topic_id), INDEX IDX_996BCC5A61220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_source (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', slug VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_topic (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', forum_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', last_post_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, type INT NOT NULL, is_locked TINYINT(1) NOT NULL, creation_datetime DATETIME NOT NULL, post_number INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_853478CC989D9B62 (slug), INDEX IDX_853478CC29CCBAD0 (forum_id), INDEX IDX_853478CC2D053F64 (last_post_id), INDEX IDX_853478CCF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fos_user (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', profile_picture_id INT DEFAULT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, last_login_datetime DATETIME DEFAULT NULL, old_id INT DEFAULT NULL, confirmation_datetime DATETIME DEFAULT NULL, token VARCHAR(255) DEFAULT NULL, reset_request_datetime DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_957A6479F85E0677 (username), UNIQUE INDEX UNIQ_957A6479E7927C74 (email), UNIQUE INDEX UNIQ_957A6479292E8AE2 (profile_picture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gallery (id INT AUTO_INCREMENT NOT NULL, author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', cover_image_id INT DEFAULT NULL, view_cache_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, publication_datetime DATETIME DEFAULT NULL, status SMALLINT NOT NULL, slug VARCHAR(255) DEFAULT NULL, INDEX IDX_472B783AF675F31B (author_id), UNIQUE INDEX UNIQ_472B783AE5A0E336 (cover_image_id), UNIQUE INDEX UNIQ_472B783AF91C231C (view_cache_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gallery_image (id INT AUTO_INCREMENT NOT NULL, gallery_id INT NOT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, creation_datetime DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_21A0D47C4E7AF8F (gallery_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', thread_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, content LONGTEXT NOT NULL, INDEX IDX_B6BD307FF675F31B (author_id), INDEX IDX_B6BD307FE2904019 (thread_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_participant (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', thread_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', participant_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, INDEX IDX_B7E035E8E2904019 (thread_id), INDEX IDX_B7E035E89D1C3019 (participant_id), UNIQUE INDEX message_participant_unique (thread_id, participant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_thread (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', last_message_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, INDEX IDX_607D18CBA0E79C3 (last_message_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_thread_meta (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', thread_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, is_read TINYINT(1) NOT NULL, is_deleted TINYINT(1) NOT NULL, INDEX IDX_333C5642E2904019 (thread_id), INDEX IDX_333C5642A76ED395 (user_id), UNIQUE INDEX message_thread_meta_unique (thread_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE musician_announce (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', instrument_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', creation_datetime DATETIME NOT NULL, type SMALLINT NOT NULL, location_name VARCHAR(255) NOT NULL, longitude VARCHAR(255) NOT NULL, latitude VARCHAR(255) NOT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_4E88BA9BF675F31B (author_id), INDEX IDX_4E88BA9BCF11D9C (instrument_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE musician_announce_style (musician_announce_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', style_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_C6BA9CDD6A4EDF4F (musician_announce_id), INDEX IDX_C6BA9CDDBACD6074 (style_id), PRIMARY KEY(musician_announce_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication (id INT AUTO_INCREMENT NOT NULL, sub_category_id INT NOT NULL, author_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', cover_id INT DEFAULT NULL, thread_id INT DEFAULT NULL, view_cache_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, short_description LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, creation_datetime DATETIME NOT NULL, edition_datetime DATETIME DEFAULT NULL, publication_datetime DATETIME DEFAULT NULL, status SMALLINT NOT NULL, type SMALLINT DEFAULT NULL, old_publication_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_AF3C6779989D9B62 (slug), INDEX IDX_AF3C6779F7BFE87C (sub_category_id), INDEX IDX_AF3C6779F675F31B (author_id), UNIQUE INDEX UNIQ_AF3C6779922726E9 (cover_id), INDEX IDX_AF3C6779E2904019 (thread_id), UNIQUE INDEX UNIQ_AF3C6779F91C231C (view_cache_id), FULLTEXT INDEX IDX_AF3C67792B36786B9BE5A5B1FEC530A9 (title, short_description, content), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_cover (id INT AUTO_INCREMENT NOT NULL, publication_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_68D6C04938B217A7 (publication_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_featured (id INT AUTO_INCREMENT NOT NULL, publication_id INT NOT NULL, cover_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, creation_datetime DATETIME NOT NULL, level SMALLINT NOT NULL, status SMALLINT NOT NULL, publication_datetime DATETIME DEFAULT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', INDEX IDX_1374AC8538B217A7 (publication_id), UNIQUE INDEX UNIQ_1374AC85922726E9 (cover_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_featured_image (id INT AUTO_INCREMENT NOT NULL, publication_featured_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4E9235DDD4655A88 (publication_featured_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_image (id INT AUTO_INCREMENT NOT NULL, publication_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_20E342D338B217A7 (publication_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_sub_category (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, position INT DEFAULT NULL, type SMALLINT DEFAULT NULL, UNIQUE INDEX UNIQ_FD30EE5D989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_profile_picture (id INT AUTO_INCREMENT NOT NULL, user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D7B9FD9AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view (id INT AUTO_INCREMENT NOT NULL, user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', view_cache_id INT NOT NULL, creation_datetime DATETIME NOT NULL, identifier VARCHAR(255) NOT NULL, INDEX IDX_FEFDAB8EA76ED395 (user_id), INDEX IDX_FEFDAB8EF91C231C (view_cache_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view_cache (id INT AUTO_INCREMENT NOT NULL, count INT NOT NULL, creation_datetime DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wiki_artist_cover (id INT AUTO_INCREMENT NOT NULL, artist_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F99C115B7970CF8 (artist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT FK_1599687922726E9 FOREIGN KEY (cover_id) REFERENCES wiki_artist_cover (id)');
        $this->addSql('ALTER TABLE artist_social ADD CONSTRAINT FK_8363EDF9B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE2904019 FOREIGN KEY (thread_id) REFERENCES comment_thread (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE forum ADD CONSTRAINT FK_852BBECD14721E40 FOREIGN KEY (forum_category_id) REFERENCES forum_category (id)');
        $this->addSql('ALTER TABLE forum_category ADD CONSTRAINT FK_21BF9426AB759837 FOREIGN KEY (forum_source_id) REFERENCES forum_source (id)');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A1F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id)');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A61220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC29CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC2D053F64 FOREIGN KEY (last_post_id) REFERENCES forum_post (id)');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE fos_user ADD CONSTRAINT FK_957A6479292E8AE2 FOREIGN KEY (profile_picture_id) REFERENCES user_profile_picture (id)');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AE5A0E336 FOREIGN KEY (cover_image_id) REFERENCES gallery_image (id)');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE gallery_image ADD CONSTRAINT FK_21A0D47C4E7AF8F FOREIGN KEY (gallery_id) REFERENCES gallery (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_participant ADD CONSTRAINT FK_B7E035E8E2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_participant ADD CONSTRAINT FK_B7E035E89D1C3019 FOREIGN KEY (participant_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE message_thread ADD CONSTRAINT FK_607D18CBA0E79C3 FOREIGN KEY (last_message_id) REFERENCES message (id)');
        $this->addSql('ALTER TABLE message_thread_meta ADD CONSTRAINT FK_333C5642E2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_thread_meta ADD CONSTRAINT FK_333C5642A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE musician_announce ADD CONSTRAINT FK_4E88BA9BF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE musician_announce ADD CONSTRAINT FK_4E88BA9BCF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id)');
        $this->addSql('ALTER TABLE musician_announce_style ADD CONSTRAINT FK_C6BA9CDD6A4EDF4F FOREIGN KEY (musician_announce_id) REFERENCES musician_announce (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE musician_announce_style ADD CONSTRAINT FK_C6BA9CDDBACD6074 FOREIGN KEY (style_id) REFERENCES attribute_style (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES publication_sub_category (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779922726E9 FOREIGN KEY (cover_id) REFERENCES publication_cover (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779E2904019 FOREIGN KEY (thread_id) REFERENCES comment_thread (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE publication_cover ADD CONSTRAINT FK_68D6C04938B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication_featured ADD CONSTRAINT FK_1374AC8538B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication_featured ADD CONSTRAINT FK_1374AC85922726E9 FOREIGN KEY (cover_id) REFERENCES publication_featured_image (id)');
        $this->addSql('ALTER TABLE publication_featured_image ADD CONSTRAINT FK_4E9235DDD4655A88 FOREIGN KEY (publication_featured_id) REFERENCES publication_featured (id)');
        $this->addSql('ALTER TABLE publication_image ADD CONSTRAINT FK_20E342D338B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE user_profile_picture ADD CONSTRAINT FK_D7B9FD9AA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8EA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8EF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE wiki_artist_cover ADD CONSTRAINT FK_6F99C115B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE artist_social DROP FOREIGN KEY FK_8363EDF9B7970CF8');
        $this->addSql('ALTER TABLE wiki_artist_cover DROP FOREIGN KEY FK_6F99C115B7970CF8');
        $this->addSql('ALTER TABLE musician_announce DROP FOREIGN KEY FK_4E88BA9BCF11D9C');
        $this->addSql('ALTER TABLE musician_announce_style DROP FOREIGN KEY FK_C6BA9CDDBACD6074');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE2904019');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779E2904019');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC29CCBAD0');
        $this->addSql('ALTER TABLE forum DROP FOREIGN KEY FK_852BBECD14721E40');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC2D053F64');
        $this->addSql('ALTER TABLE forum_category DROP FOREIGN KEY FK_21BF9426AB759837');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5A1F55203D');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5A61220EA6');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCF675F31B');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AF675F31B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF675F31B');
        $this->addSql('ALTER TABLE message_participant DROP FOREIGN KEY FK_B7E035E89D1C3019');
        $this->addSql('ALTER TABLE message_thread_meta DROP FOREIGN KEY FK_333C5642A76ED395');
        $this->addSql('ALTER TABLE musician_announce DROP FOREIGN KEY FK_4E88BA9BF675F31B');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779F675F31B');
        $this->addSql('ALTER TABLE user_profile_picture DROP FOREIGN KEY FK_D7B9FD9AA76ED395');
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY FK_FEFDAB8EA76ED395');
        $this->addSql('ALTER TABLE gallery_image DROP FOREIGN KEY FK_21A0D47C4E7AF8F');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AE5A0E336');
        $this->addSql('ALTER TABLE message_thread DROP FOREIGN KEY FK_607D18CBA0E79C3');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FE2904019');
        $this->addSql('ALTER TABLE message_participant DROP FOREIGN KEY FK_B7E035E8E2904019');
        $this->addSql('ALTER TABLE message_thread_meta DROP FOREIGN KEY FK_333C5642E2904019');
        $this->addSql('ALTER TABLE musician_announce_style DROP FOREIGN KEY FK_C6BA9CDD6A4EDF4F');
        $this->addSql('ALTER TABLE publication_cover DROP FOREIGN KEY FK_68D6C04938B217A7');
        $this->addSql('ALTER TABLE publication_featured DROP FOREIGN KEY FK_1374AC8538B217A7');
        $this->addSql('ALTER TABLE publication_image DROP FOREIGN KEY FK_20E342D338B217A7');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779922726E9');
        $this->addSql('ALTER TABLE publication_featured_image DROP FOREIGN KEY FK_4E9235DDD4655A88');
        $this->addSql('ALTER TABLE publication_featured DROP FOREIGN KEY FK_1374AC85922726E9');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779F7BFE87C');
        $this->addSql('ALTER TABLE fos_user DROP FOREIGN KEY FK_957A6479292E8AE2');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AF91C231C');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779F91C231C');
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY FK_FEFDAB8EF91C231C');
        $this->addSql('ALTER TABLE artist DROP FOREIGN KEY FK_1599687922726E9');
        $this->addSql('DROP TABLE artist');
        $this->addSql('DROP TABLE artist_social');
        $this->addSql('DROP TABLE attribute_instrument');
        $this->addSql('DROP TABLE attribute_style');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE comment_thread');
        $this->addSql('DROP TABLE forum');
        $this->addSql('DROP TABLE forum_category');
        $this->addSql('DROP TABLE forum_post');
        $this->addSql('DROP TABLE forum_source');
        $this->addSql('DROP TABLE forum_topic');
        $this->addSql('DROP TABLE fos_user');
        $this->addSql('DROP TABLE gallery');
        $this->addSql('DROP TABLE gallery_image');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE message_participant');
        $this->addSql('DROP TABLE message_thread');
        $this->addSql('DROP TABLE message_thread_meta');
        $this->addSql('DROP TABLE musician_announce');
        $this->addSql('DROP TABLE musician_announce_style');
        $this->addSql('DROP TABLE publication');
        $this->addSql('DROP TABLE publication_cover');
        $this->addSql('DROP TABLE publication_featured');
        $this->addSql('DROP TABLE publication_featured_image');
        $this->addSql('DROP TABLE publication_image');
        $this->addSql('DROP TABLE publication_sub_category');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE user_profile_picture');
        $this->addSql('DROP TABLE view');
        $this->addSql('DROP TABLE view_cache');
        $this->addSql('DROP TABLE wiki_artist_cover');
    }
}
