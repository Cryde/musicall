<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_verification_code table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_verification_code (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            hashed_code VARCHAR(255) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            expiration_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            used_datetime DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            creation_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_EMAILVERIF_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE email_verification_code ADD CONSTRAINT FK_EMAILVERIF_USER FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_verification_code DROP FOREIGN KEY FK_EMAILVERIF_USER');
        $this->addSql('DROP TABLE email_verification_code');
    }
}
