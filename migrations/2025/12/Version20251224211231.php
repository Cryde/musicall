<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251224211231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE band_space (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE band_space_membership (id CHAR(36) NOT NULL, role VARCHAR(255) NOT NULL, creation_datetime DATETIME NOT NULL, band_space_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, INDEX IDX_7F56B6A5E31C124A (band_space_id), INDEX IDX_7F56B6A5A76ED395 (user_id), UNIQUE INDEX unique_band_space_user (band_space_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_membership ADD CONSTRAINT FK_7F56B6A5E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_membership ADD CONSTRAINT FK_7F56B6A5A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE band_space_membership DROP FOREIGN KEY FK_7F56B6A5E31C124A');
        $this->addSql('ALTER TABLE band_space_membership DROP FOREIGN KEY FK_7F56B6A5A76ED395');
        $this->addSql('DROP TABLE band_space');
        $this->addSql('DROP TABLE band_space_membership');
    }
}
