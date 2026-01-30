<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115130551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_teacher_profile_package (id CHAR(36) NOT NULL, title VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, sessions_count INT DEFAULT NULL, price INT NOT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_F554CACC46E5B018 (teacher_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_teacher_profile_package ADD CONSTRAINT FK_F554CACC46E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_teacher_profile_location ADD address VARCHAR(255) DEFAULT NULL, ADD latitude VARCHAR(255) DEFAULT NULL, ADD longitude VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_profile_package DROP FOREIGN KEY FK_F554CACC46E5B018');
        $this->addSql('DROP TABLE user_teacher_profile_package');
        $this->addSql('ALTER TABLE user_teacher_profile_location DROP address, DROP latitude, DROP longitude');
    }
}
