<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260111141148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create musician profile tables (user_musician_profile, user_musician_profile_style, user_musician_profile_instrument)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_musician_profile (id CHAR(36) NOT NULL, availability_status VARCHAR(50) DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, user_id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_C5FA537FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_musician_profile_style (musician_profile_id CHAR(36) NOT NULL, style_id CHAR(36) NOT NULL, INDEX IDX_42FDFC91DFD1907F (musician_profile_id), INDEX IDX_42FDFC91BACD6074 (style_id), PRIMARY KEY (musician_profile_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_musician_profile_instrument (id CHAR(36) NOT NULL, skill_level VARCHAR(20) NOT NULL, musician_profile_id CHAR(36) NOT NULL, instrument_id CHAR(36) NOT NULL, INDEX IDX_5193028CDFD1907F (musician_profile_id), INDEX IDX_5193028CCF11D9C (instrument_id), UNIQUE INDEX musician_profile_instrument_unique (musician_profile_id, instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_musician_profile ADD CONSTRAINT FK_C5FA537FA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE user_musician_profile_style ADD CONSTRAINT FK_42FDFC91DFD1907F FOREIGN KEY (musician_profile_id) REFERENCES user_musician_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_musician_profile_style ADD CONSTRAINT FK_42FDFC91BACD6074 FOREIGN KEY (style_id) REFERENCES attribute_style (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_musician_profile_instrument ADD CONSTRAINT FK_5193028CDFD1907F FOREIGN KEY (musician_profile_id) REFERENCES user_musician_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_musician_profile_instrument ADD CONSTRAINT FK_5193028CCF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_musician_profile DROP FOREIGN KEY FK_C5FA537FA76ED395');
        $this->addSql('ALTER TABLE user_musician_profile_style DROP FOREIGN KEY FK_42FDFC91DFD1907F');
        $this->addSql('ALTER TABLE user_musician_profile_style DROP FOREIGN KEY FK_42FDFC91BACD6074');
        $this->addSql('ALTER TABLE user_musician_profile_instrument DROP FOREIGN KEY FK_5193028CDFD1907F');
        $this->addSql('ALTER TABLE user_musician_profile_instrument DROP FOREIGN KEY FK_5193028CCF11D9C');
        $this->addSql('DROP TABLE user_musician_profile');
        $this->addSql('DROP TABLE user_musician_profile_style');
        $this->addSql('DROP TABLE user_musician_profile_instrument');
    }
}
