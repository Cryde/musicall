<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115122842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_teacher_profile_location (id CHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, radius INT DEFAULT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_4C025C3646E5B018 (teacher_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_teacher_profile_location ADD CONSTRAINT FK_4C025C3646E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_profile_location DROP FOREIGN KEY FK_4C025C3646E5B018');
        $this->addSql('DROP TABLE user_teacher_profile_location');
    }
}
