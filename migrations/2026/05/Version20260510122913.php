<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510122913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_band_space_file_version_lookup ON band_space_file_version');
        $this->addSql('ALTER TABLE band_space_invitation CHANGE id id CHAR(36) NOT NULL, CHANGE band_space_id band_space_id CHAR(36) NOT NULL, CHANGE invited_by_id invited_by_id CHAR(36) NOT NULL, CHANGE existing_user_id existing_user_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX uniq_band_space_invitation_token TO UNIQ_8B405825F37A13B');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_band_space_invitation_band_space TO IDX_8B40582E31C124A');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_band_space_invitation_invited_by TO IDX_8B40582A7B4A7E3');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_band_space_invitation_existing_user TO IDX_8B40582E3E4AD5');
        $this->addSql('ALTER TABLE band_space_membership CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE comment RENAME INDEX uniq_9474526c83c5b397 TO UNIQ_9474526C5A02CC22');
        $this->addSql('ALTER TABLE email_verification_code CHANGE id id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL, CHANGE attempts attempts INT NOT NULL, CHANGE expiration_datetime expiration_datetime DATETIME NOT NULL, CHANGE used_datetime used_datetime DATETIME DEFAULT NULL, CHANGE creation_datetime creation_datetime DATETIME NOT NULL');
        $this->addSql('ALTER TABLE email_verification_code RENAME INDEX idx_emailverif_user TO IDX_BD2ADC58A76ED395');
        $this->addSql('ALTER TABLE finance_entry RENAME INDEX idx_finance_entry_recurrence TO IDX_2B8E898B2C414CE8');
        $this->addSql('ALTER TABLE finance_recurrence RENAME INDEX idx_finance_recurrence_category TO IDX_2D0C49F812469DE2');
        $this->addSql('ALTER TABLE forum_post RENAME INDEX uniq_996bcc5a83c5b397 TO UNIQ_996BCC5A5A02CC22');
        $this->addSql('ALTER TABLE publication DROP INDEX IDX_AF3C6779CACB62F1, ADD UNIQUE INDEX UNIQ_AF3C67795A02CC22 (vote_cache_id)');
        $this->addSql('ALTER TABLE task CHANGE completed_datetime completed_datetime DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE vote RENAME INDEX idx_5a108564cacb62f1 TO IDX_5A1085645A02CC22');
        $this->addSql('ALTER TABLE vote_cache CHANGE upvote_count upvote_count INT NOT NULL, CHANGE downvote_count downvote_count INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_band_space_file_version_lookup ON band_space_file_version (band_space_file_id, version_number)');
        $this->addSql('ALTER TABLE band_space_invitation CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE band_space_id band_space_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE invited_by_id invited_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE existing_user_id existing_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_8b40582a7b4a7e3 TO IDX_band_space_invitation_invited_by');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_8b40582e3e4ad5 TO IDX_band_space_invitation_existing_user');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX uniq_8b405825f37a13b TO UNIQ_band_space_invitation_token');
        $this->addSql('ALTER TABLE band_space_invitation RENAME INDEX idx_8b40582e31c124a TO IDX_band_space_invitation_band_space');
        $this->addSql('ALTER TABLE band_space_membership CHANGE status status VARCHAR(255) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE comment RENAME INDEX uniq_9474526c5a02cc22 TO UNIQ_9474526C83C5B397');
        $this->addSql('ALTER TABLE email_verification_code CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE attempts attempts INT DEFAULT 0 NOT NULL, CHANGE expiration_datetime expiration_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE used_datetime used_datetime DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE creation_datetime creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE email_verification_code RENAME INDEX idx_bd2adc58a76ed395 TO IDX_EMAILVERIF_USER');
        $this->addSql('ALTER TABLE finance_entry RENAME INDEX idx_2b8e898b2c414ce8 TO IDX_finance_entry_recurrence');
        $this->addSql('ALTER TABLE finance_recurrence RENAME INDEX idx_2d0c49f812469de2 TO IDX_finance_recurrence_category');
        $this->addSql('ALTER TABLE forum_post RENAME INDEX uniq_996bcc5a5a02cc22 TO UNIQ_996BCC5A83C5B397');
        $this->addSql('ALTER TABLE publication DROP INDEX UNIQ_AF3C67795A02CC22, ADD INDEX IDX_AF3C6779CACB62F1 (vote_cache_id)');
        $this->addSql('ALTER TABLE task CHANGE completed_datetime completed_datetime DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vote RENAME INDEX idx_5a1085645a02cc22 TO IDX_5A108564CACB62F1');
        $this->addSql('ALTER TABLE vote_cache CHANGE upvote_count upvote_count INT DEFAULT 0 NOT NULL, CHANGE downvote_count downvote_count INT DEFAULT 0 NOT NULL');
    }
}
