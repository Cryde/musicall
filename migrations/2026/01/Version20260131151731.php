<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131151731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TeacherSocialLink table for teacher profile social links';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_teacher_social_link (id INT AUTO_INCREMENT NOT NULL, platform VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, creation_datetime DATETIME NOT NULL, teacher_profile_id CHAR(36) NOT NULL, INDEX IDX_852299AD46E5B018 (teacher_profile_id), UNIQUE INDEX unique_teacher_profile_platform (teacher_profile_id, platform), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_teacher_social_link ADD CONSTRAINT FK_852299AD46E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teacher_social_link DROP FOREIGN KEY FK_852299AD46E5B018');
        $this->addSql('DROP TABLE user_teacher_social_link');
    }
}
