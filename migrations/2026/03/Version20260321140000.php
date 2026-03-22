<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_invitation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_invitation (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            band_space_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            invited_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            existing_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            creation_datetime DATETIME NOT NULL,
            expiration_datetime DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_band_space_invitation_token (token),
            INDEX IDX_band_space_invitation_band_space (band_space_id),
            INDEX IDX_band_space_invitation_invited_by (invited_by_id),
            INDEX IDX_band_space_invitation_existing_user (existing_user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE band_space_invitation ADD CONSTRAINT FK_band_space_invitation_band_space FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_invitation ADD CONSTRAINT FK_band_space_invitation_invited_by FOREIGN KEY (invited_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE band_space_invitation ADD CONSTRAINT FK_band_space_invitation_existing_user FOREIGN KEY (existing_user_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE band_space_invitation');
    }
}
