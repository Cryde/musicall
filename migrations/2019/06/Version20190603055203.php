<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190603055203 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_957A647992FC23A8 ON fos_user');
        $this->addSql('DROP INDEX UNIQ_957A6479C05FB297 ON fos_user');
        $this->addSql('DROP INDEX UNIQ_957A6479A0D96FBF ON fos_user');
        $this->addSql('ALTER TABLE fos_user ADD creation_datetime DATETIME NOT NULL, ADD last_login_datetime DATETIME DEFAULT NULL, DROP username_canonical, DROP email_canonical, DROP enabled, DROP salt, DROP last_login, DROP confirmation_token, DROP password_requested_at, CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_957A6479F85E0677 ON fos_user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_957A6479E7927C74 ON fos_user (email)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_957A6479F85E0677 ON fos_user');
        $this->addSql('DROP INDEX UNIQ_957A6479E7927C74 ON fos_user');
        $this->addSql('ALTER TABLE fos_user ADD username_canonical VARCHAR(180) NOT NULL COLLATE utf8mb4_unicode_ci, ADD email_canonical VARCHAR(180) NOT NULL COLLATE utf8mb4_unicode_ci, ADD enabled TINYINT(1) NOT NULL, ADD salt VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD confirmation_token VARCHAR(180) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD password_requested_at DATETIME DEFAULT NULL, DROP creation_datetime, CHANGE roles roles LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:array)\', CHANGE last_login_datetime last_login DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_957A647992FC23A8 ON fos_user (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_957A6479C05FB297 ON fos_user (confirmation_token)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_957A6479A0D96FBF ON fos_user (email_canonical)');
    }
}
