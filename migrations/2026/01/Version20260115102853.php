<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115102853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_teacher_availability (id CHAR(36) NOT NULL, day_of_week VARCHAR(10) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_174985F246E5B018 (teacher_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_teacher_profile (id CHAR(36) NOT NULL, description LONGTEXT DEFAULT NULL, years_of_experience INT DEFAULT NULL, hourly_rate INT DEFAULT NULL, lesson_type VARCHAR(20) DEFAULT NULL, student_levels JSON NOT NULL, age_groups JSON NOT NULL, course_title VARCHAR(255) DEFAULT NULL, offers_trial TINYINT NOT NULL, trial_price INT DEFAULT NULL, location_city VARCHAR(100) DEFAULT NULL, location_country VARCHAR(100) DEFAULT NULL, can_travel TINYINT NOT NULL, travel_distance INT DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, user_id CHAR(36) NOT NULL, view_cache_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5E494BC1A76ED395 (user_id), UNIQUE INDEX UNIQ_5E494BC1F91C231C (view_cache_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_teacher_profile_style (teacher_profile_id CHAR(36) NOT NULL, style_id CHAR(36) NOT NULL, INDEX IDX_CC2CE4B246E5B018 (teacher_profile_id), INDEX IDX_CC2CE4B2BACD6074 (style_id), PRIMARY KEY (teacher_profile_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_teacher_profile_instrument (id CHAR(36) NOT NULL, teacher_profile_id CHAR(36) NOT NULL, instrument_id CHAR(36) NOT NULL, INDEX IDX_6B14C2EA46E5B018 (teacher_profile_id), INDEX IDX_6B14C2EACF11D9C (instrument_id), UNIQUE INDEX teacher_profile_instrument_unique (teacher_profile_id, instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_teacher_profile_media (id CHAR(36) NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, embed_id VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, thumbnail_image_name VARCHAR(255) DEFAULT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_95BDFDD446E5B018 (teacher_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_teacher_profile_pricing (id CHAR(36) NOT NULL, duration VARCHAR(10) NOT NULL, price INT NOT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_CECD016A46E5B018 (teacher_profile_id), UNIQUE INDEX teacher_profile_duration_unique (teacher_profile_id, duration), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_teacher_availability ADD CONSTRAINT FK_174985F246E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile ADD CONSTRAINT FK_5E494BC1A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE user_teacher_profile ADD CONSTRAINT FK_5E494BC1F91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE user_teacher_profile_style ADD CONSTRAINT FK_CC2CE4B246E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile_style ADD CONSTRAINT FK_CC2CE4B2BACD6074 FOREIGN KEY (style_id) REFERENCES attribute_style (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile_instrument ADD CONSTRAINT FK_6B14C2EA46E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile_instrument ADD CONSTRAINT FK_6B14C2EACF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id)');
        $this->addSql('ALTER TABLE user_teacher_profile_media ADD CONSTRAINT FK_95BDFDD446E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile_pricing ADD CONSTRAINT FK_CECD016A46E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_availability DROP FOREIGN KEY FK_174985F246E5B018');
        $this->addSql('ALTER TABLE user_teacher_profile DROP FOREIGN KEY FK_5E494BC1A76ED395');
        $this->addSql('ALTER TABLE user_teacher_profile DROP FOREIGN KEY FK_5E494BC1F91C231C');
        $this->addSql('ALTER TABLE user_teacher_profile_style DROP FOREIGN KEY FK_CC2CE4B246E5B018');
        $this->addSql('ALTER TABLE user_teacher_profile_style DROP FOREIGN KEY FK_CC2CE4B2BACD6074');
        $this->addSql('ALTER TABLE user_teacher_profile_instrument DROP FOREIGN KEY FK_6B14C2EA46E5B018');
        $this->addSql('ALTER TABLE user_teacher_profile_instrument DROP FOREIGN KEY FK_6B14C2EACF11D9C');
        $this->addSql('ALTER TABLE user_teacher_profile_media DROP FOREIGN KEY FK_95BDFDD446E5B018');
        $this->addSql('ALTER TABLE user_teacher_profile_pricing DROP FOREIGN KEY FK_CECD016A46E5B018');
        $this->addSql('DROP TABLE user_teacher_availability');
        $this->addSql('DROP TABLE user_teacher_profile');
        $this->addSql('DROP TABLE user_teacher_profile_style');
        $this->addSql('DROP TABLE user_teacher_profile_instrument');
        $this->addSql('DROP TABLE user_teacher_profile_media');
        $this->addSql('DROP TABLE user_teacher_profile_pricing');
    }
}
