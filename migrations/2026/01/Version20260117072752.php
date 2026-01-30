<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117072752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused columns from teacher profile: hourly_rate, lesson_type, location_city, location_country, can_travel, travel_distance';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_profile DROP hourly_rate, DROP lesson_type, DROP location_city, DROP location_country, DROP can_travel, DROP travel_distance');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_profile ADD hourly_rate INT DEFAULT NULL, ADD lesson_type VARCHAR(20) DEFAULT NULL, ADD location_city VARCHAR(100) DEFAULT NULL, ADD location_country VARCHAR(100) DEFAULT NULL, ADD can_travel TINYINT NOT NULL, ADD travel_distance INT DEFAULT NULL');
    }
}
