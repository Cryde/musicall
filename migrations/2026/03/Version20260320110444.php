<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320110444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_musician_profile RENAME INDEX uniq_c5fa537fa76ed395 TO unique_musician_profile_user');
        $this->addSql('ALTER TABLE user_teacher_profile RENAME INDEX uniq_5e494bc1a76ed395 TO unique_teacher_profile_user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_musician_profile RENAME INDEX unique_musician_profile_user TO UNIQ_C5FA537FA76ED395');
        $this->addSql('ALTER TABLE user_teacher_profile RENAME INDEX unique_teacher_profile_user TO UNIQ_5E494BC1A76ED395');
    }
}
