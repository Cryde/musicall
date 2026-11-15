<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The teacher profile becomes a teaching service (#1318): every row is copied with its id, so the contact origins,
 * the media links and the view counts stay valid. The user_teacher_* tables stay in place, unused, until #1405.
 */
final class Version20261115132000 extends AbstractMigration
{
    /** Each old table and the rows it became. */
    private const array COPIES = [
        'user_teacher_profile' => 'service',
        'user_teacher_profile_instrument' => 'service_instrument',
        'user_teacher_profile_style' => 'service_style',
        'user_teacher_profile_media' => 'service_media',
        'user_teacher_profile_location' => 'service_place',
        'user_teacher_profile_pricing' => "service_offer WHERE unit = 'lesson'",
        'user_teacher_profile_package' => "service_offer WHERE unit = 'package'",
        'user_teacher_availability' => 'service_availability',
        'user_teacher_social_link' => 'service_link',
    ];

    /** Per old table, its rows with no new row holding the same values. BINARY: the columns compare case blind. */
    private const array MISMATCHES = [
        'user_teacher_profile' => "SELECT COUNT(*) FROM user_teacher_profile t LEFT JOIN service s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.kind = 'teaching' AND s.user_id <=> t.user_id AND BINARY s.headline <=> BINARY t.course_title
                AND BINARY s.description <=> BINARY t.description AND s.years_of_experience <=> t.years_of_experience
                AND s.creation_datetime <=> t.creation_datetime AND s.update_datetime <=> t.update_datetime AND s.view_cache_id <=> t.view_cache_id
                AND JSON_EQUALS(JSON_EXTRACT(s.details, '$.student_levels'), t.student_levels) AND JSON_EQUALS(JSON_EXTRACT(s.details, '$.age_groups'), t.age_groups)
                AND JSON_EQUALS(JSON_EXTRACT(s.details, '$.level_goals'), t.level_goals) AND JSON_EQUALS(JSON_EXTRACT(s.details, '$.songs'), t.songs)
                AND JSON_VALUE(s.details, '$.offers_trial') = (t.offers_trial = 1) AND JSON_VALUE(s.details, '$.trial_price') <=> t.trial_price)",
        'user_teacher_profile_instrument' => 'SELECT COUNT(*) FROM user_teacher_profile_instrument t LEFT JOIN service_instrument s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND s.instrument_id <=> t.instrument_id)',
        'user_teacher_profile_style' => 'SELECT COUNT(*) FROM user_teacher_profile_style t LEFT JOIN service_style s ON s.service_id = t.teacher_profile_id AND s.style_id = t.style_id
            WHERE s.service_id IS NULL',
        'user_teacher_profile_media' => 'SELECT COUNT(*) FROM user_teacher_profile_media t LEFT JOIN service_media s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND BINARY s.platform <=> BINARY t.platform AND BINARY s.url <=> BINARY t.url
                AND BINARY s.embed_id <=> BINARY t.embed_id AND BINARY s.title <=> BINARY t.title AND BINARY s.thumbnail_image_name <=> BINARY t.thumbnail_image_name
                AND s.position <=> t.position AND s.creation_datetime <=> t.creation_datetime)',
        'user_teacher_profile_location' => "SELECT COUNT(*) FROM user_teacher_profile_location t LEFT JOIN service_place s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id
                AND s.type = CASE t.type WHEN 'teacher_place' THEN 'at_my_place' WHEN 'student_place' THEN 'at_client_place' WHEN 'online' THEN 'remote' END
                AND BINARY s.address <=> BINARY t.address AND BINARY s.city <=> BINARY t.city AND BINARY s.country <=> BINARY t.country
                AND BINARY s.latitude <=> BINARY t.latitude AND BINARY s.longitude <=> BINARY t.longitude AND s.radius <=> t.radius)",
        'user_teacher_profile_pricing' => "SELECT COUNT(*) FROM user_teacher_profile_pricing t LEFT JOIN service_offer s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND s.unit = 'lesson' AND s.price <=> t.price
                AND s.quantity = CASE t.duration WHEN '30min' THEN 30 WHEN '1h' THEN 60 WHEN '1h30' THEN 90 WHEN '2h' THEN 120 END)",
        'user_teacher_profile_package' => "SELECT COUNT(*) FROM user_teacher_profile_package t LEFT JOIN service_offer s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND s.unit = 'package' AND BINARY s.title <=> BINARY t.title
                AND BINARY s.description <=> BINARY t.description AND s.quantity <=> t.sessions_count AND s.price <=> t.price)",
        'user_teacher_availability' => 'SELECT COUNT(*) FROM user_teacher_availability t LEFT JOIN service_availability s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND BINARY s.day_of_week <=> BINARY t.day_of_week
                AND s.start_time <=> t.start_time AND s.end_time <=> t.end_time)',
        'user_teacher_social_link' => 'SELECT COUNT(*) FROM user_teacher_social_link t LEFT JOIN service_link s ON s.id = t.id
            WHERE s.id IS NULL OR NOT (s.service_id <=> t.teacher_profile_id AND BINARY s.platform <=> BINARY t.platform
                AND BINARY s.url <=> BINARY t.url AND s.creation_datetime <=> t.creation_datetime)',
    ];

    public function getDescription(): string
    {
        return 'Copy the teacher profiles into the service tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service (id CHAR(36) NOT NULL, kind VARCHAR(30) NOT NULL, headline VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, years_of_experience INT DEFAULT NULL, specialities JSON NOT NULL, details JSON NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, user_id CHAR(36) NOT NULL, view_cache_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_E19D9AD2F91C231C (view_cache_id), UNIQUE INDEX unique_service_user_kind (user_id, kind), INDEX IDX_E19D9AD2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_style (service_id CHAR(36) NOT NULL, style_id CHAR(36) NOT NULL, INDEX IDX_9ACF558DED5CA9E6 (service_id), INDEX IDX_9ACF558DBACD6074 (style_id), PRIMARY KEY (service_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_availability (id CHAR(36) NOT NULL, day_of_week VARCHAR(10) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, service_id CHAR(36) NOT NULL, INDEX IDX_E01BD75AED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_instrument (id CHAR(36) NOT NULL, service_id CHAR(36) NOT NULL, instrument_id CHAR(36) NOT NULL, UNIQUE INDEX unique_service_instrument (service_id, instrument_id), INDEX IDX_AAEE5069ED5CA9E6 (service_id), INDEX IDX_AAEE5069CF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_link (id INT AUTO_INCREMENT NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, creation_datetime DATETIME NOT NULL, service_id CHAR(36) NOT NULL, UNIQUE INDEX unique_service_link_platform (service_id, platform), INDEX IDX_F8EF2D1DED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_media (id CHAR(36) NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, embed_id VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, thumbnail_image_name VARCHAR(255) DEFAULT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL, service_id CHAR(36) NOT NULL, INDEX IDX_C35E4CEBED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_offer (id CHAR(36) NOT NULL, unit VARCHAR(20) NOT NULL, quantity INT DEFAULT NULL, price INT NOT NULL, title VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, service_id CHAR(36) NOT NULL, INDEX IDX_80A46AD9ED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_place (id CHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, latitude VARCHAR(255) DEFAULT NULL, longitude VARCHAR(255) DEFAULT NULL, radius INT DEFAULT NULL, service_id CHAR(36) NOT NULL, INDEX IDX_DD6FBE2AED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2F91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('ALTER TABLE service_style ADD CONSTRAINT FK_9ACF558DED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_style ADD CONSTRAINT FK_9ACF558DBACD6074 FOREIGN KEY (style_id) REFERENCES attribute_style (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_availability ADD CONSTRAINT FK_E01BD75AED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_instrument ADD CONSTRAINT FK_AAEE5069ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_instrument ADD CONSTRAINT FK_AAEE5069CF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id)');
        $this->addSql('ALTER TABLE service_link ADD CONSTRAINT FK_F8EF2D1DED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_media ADD CONSTRAINT FK_C35E4CEBED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_offer ADD CONSTRAINT FK_80A46AD9ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_place ADD CONSTRAINT FK_DD6FBE2AED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');

        // JSON_EXTRACT(column, '$') embeds a stored JSON value as JSON rather than as a string.
        $this->addSql("INSERT INTO service (id, user_id, kind, headline, description, years_of_experience, specialities, details, creation_datetime, update_datetime, view_cache_id)
            SELECT id, user_id, 'teaching', course_title, description, years_of_experience, '[]',
                JSON_OBJECT(
                    'student_levels', JSON_EXTRACT(student_levels, '$'),
                    'age_groups', JSON_EXTRACT(age_groups, '$'),
                    'level_goals', JSON_EXTRACT(level_goals, '$'),
                    'songs', JSON_EXTRACT(songs, '$'),
                    'offers_trial', offers_trial = 1,
                    'trial_price', trial_price
                ),
                creation_datetime, update_datetime, view_cache_id
            FROM user_teacher_profile");
        $this->addSql('INSERT INTO service_instrument (id, service_id, instrument_id) SELECT id, teacher_profile_id, instrument_id FROM user_teacher_profile_instrument');
        $this->addSql('INSERT INTO service_style (service_id, style_id) SELECT teacher_profile_id, style_id FROM user_teacher_profile_style');
        $this->addSql('INSERT INTO service_media (id, service_id, platform, url, embed_id, title, thumbnail_image_name, position, creation_datetime) SELECT id, teacher_profile_id, platform, url, embed_id, title, thumbnail_image_name, position, creation_datetime FROM user_teacher_profile_media');
        $this->addSql("INSERT INTO service_place (id, service_id, type, address, city, country, latitude, longitude, radius)
            SELECT id, teacher_profile_id, CASE type WHEN 'teacher_place' THEN 'at_my_place' WHEN 'student_place' THEN 'at_client_place' ELSE 'remote' END, address, city, country, latitude, longitude, radius
            FROM user_teacher_profile_location");
        $this->addSql("INSERT INTO service_offer (id, service_id, unit, quantity, price)
            SELECT id, teacher_profile_id, 'lesson', CASE duration WHEN '30min' THEN 30 WHEN '1h' THEN 60 WHEN '1h30' THEN 90 ELSE 120 END, price
            FROM user_teacher_profile_pricing");
        $this->addSql("INSERT INTO service_offer (id, service_id, unit, quantity, price, title, description)
            SELECT id, teacher_profile_id, 'package', sessions_count, price, title, description
            FROM user_teacher_profile_package");
        $this->addSql('INSERT INTO service_availability (id, service_id, day_of_week, start_time, end_time) SELECT id, teacher_profile_id, day_of_week, start_time, end_time FROM user_teacher_availability');
        $this->addSql('INSERT INTO service_link (id, service_id, platform, url, creation_datetime) SELECT id, teacher_profile_id, platform, url, creation_datetime FROM user_teacher_social_link');

        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY `FK_10F435D746E5B018`');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT FK_10F435D746E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES service (id) ON DELETE SET NULL');
    }

    /** Runs once the copy is done and before the version is recorded: a difference stops the migration, and the deploy with it. */
    public function postUp(Schema $schema): void
    {
        foreach (self::COPIES as $old => $new) {
            $oldCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $old);
            $newCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $new);
            $this->abortIf($oldCount !== $newCount, sprintf('%s holds %d rows, its copy %d', $old, $oldCount, $newCount));
        }
        foreach (self::MISMATCHES as $old => $sql) {
            $mismatches = (int) $this->connection->fetchOne($sql);
            $this->abortIf($mismatches > 0, sprintf('%d rows of %s differ from their copy', $mismatches, $old));
        }

        // After the check, which compares it: the view cache moved with the profile, and a service deletes it with
        // itself, which the old row's foreign key would refuse.
        $this->connection->executeStatement('UPDATE user_teacher_profile SET view_cache_id = NULL');
    }

    public function down(Schema $schema): void
    {
        // The old rows are rewritten from the teaching services, which may have changed since: their children follow by cascade.
        $this->addSql('DELETE FROM user_teacher_profile');
        $this->addSql("INSERT INTO user_teacher_profile (id, user_id, course_title, description, years_of_experience, student_levels, age_groups, level_goals, songs, offers_trial, trial_price, creation_datetime, update_datetime, view_cache_id)
            SELECT id, user_id, headline, description, years_of_experience,
                COALESCE(JSON_EXTRACT(details, '$.student_levels'), '[]'),
                COALESCE(JSON_EXTRACT(details, '$.age_groups'), '[]'),
                COALESCE(JSON_EXTRACT(details, '$.level_goals'), '[]'),
                COALESCE(JSON_EXTRACT(details, '$.songs'), '[]'),
                IF(JSON_VALUE(details, '$.offers_trial') = 1, 1, 0),
                JSON_VALUE(details, '$.trial_price'),
                creation_datetime, update_datetime, view_cache_id
            FROM service WHERE kind = 'teaching'");
        $this->addSql('INSERT INTO user_teacher_profile_instrument (id, teacher_profile_id, instrument_id) SELECT id, service_id, instrument_id FROM service_instrument WHERE service_id IN (SELECT id FROM service WHERE kind = \'teaching\')');
        $this->addSql('INSERT INTO user_teacher_profile_style (teacher_profile_id, style_id) SELECT service_id, style_id FROM service_style WHERE service_id IN (SELECT id FROM service WHERE kind = \'teaching\')');
        $this->addSql('INSERT INTO user_teacher_profile_media (id, teacher_profile_id, platform, url, embed_id, title, thumbnail_image_name, position, creation_datetime) SELECT id, service_id, platform, url, embed_id, title, thumbnail_image_name, position, creation_datetime FROM service_media WHERE service_id IN (SELECT id FROM service WHERE kind = \'teaching\')');
        $this->addSql("INSERT INTO user_teacher_profile_location (id, teacher_profile_id, type, address, city, country, latitude, longitude, radius)
            SELECT id, service_id, CASE type WHEN 'at_my_place' THEN 'teacher_place' WHEN 'at_client_place' THEN 'student_place' ELSE 'online' END, address, city, country, latitude, longitude, radius
            FROM service_place WHERE service_id IN (SELECT id FROM service WHERE kind = 'teaching')");
        $this->addSql("INSERT INTO user_teacher_profile_pricing (id, teacher_profile_id, duration, price)
            SELECT id, service_id, CASE quantity WHEN 30 THEN '30min' WHEN 60 THEN '1h' WHEN 90 THEN '1h30' ELSE '2h' END, price
            FROM service_offer WHERE unit = 'lesson' AND service_id IN (SELECT id FROM service WHERE kind = 'teaching')");
        $this->addSql("INSERT INTO user_teacher_profile_package (id, teacher_profile_id, title, description, sessions_count, price)
            SELECT id, service_id, COALESCE(title, ''), description, quantity, price
            FROM service_offer WHERE unit = 'package' AND service_id IN (SELECT id FROM service WHERE kind = 'teaching')");
        $this->addSql('INSERT INTO user_teacher_availability (id, teacher_profile_id, day_of_week, start_time, end_time) SELECT id, service_id, day_of_week, start_time, end_time FROM service_availability WHERE service_id IN (SELECT id FROM service WHERE kind = \'teaching\')');
        $this->addSql('INSERT INTO user_teacher_social_link (id, teacher_profile_id, platform, url, creation_datetime) SELECT id, service_id, platform, url, creation_datetime FROM service_link WHERE service_id IN (SELECT id FROM service WHERE kind = \'teaching\')');

        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY FK_10F435D746E5B018');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT `FK_10F435D746E5B018` FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE SET NULL');

        $this->addSql('DROP TABLE service_style');
        $this->addSql('DROP TABLE service_availability');
        $this->addSql('DROP TABLE service_instrument');
        $this->addSql('DROP TABLE service_link');
        $this->addSql('DROP TABLE service_media');
        $this->addSql('DROP TABLE service_offer');
        $this->addSql('DROP TABLE service_place');
        $this->addSql('DROP TABLE service');
    }
}
