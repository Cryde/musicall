<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322224712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE finance_category (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, position INT NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, INDEX IDX_D71AECDCE31C124A (band_space_id), INDEX IDX_D71AECDC727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE finance_entry (id CHAR(36) NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, amount INT DEFAULT NULL, amount_min INT DEFAULT NULL, amount_max INT DEFAULT NULL, date DATETIME DEFAULT NULL, scope VARCHAR(255) NOT NULL, is_recurring TINYINT NOT NULL, recurrence_interval VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, category_id CHAR(36) NOT NULL, member_id CHAR(36) DEFAULT NULL, INDEX IDX_2B8E898B12469DE2 (category_id), INDEX IDX_2B8E898B7597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE finance_entry_split (id CHAR(36) NOT NULL, amount INT NOT NULL, creation_datetime DATETIME NOT NULL, entry_id CHAR(36) NOT NULL, member_id CHAR(36) DEFAULT NULL, INDEX IDX_CAD2287CBA364942 (entry_id), INDEX IDX_CAD2287C7597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE finance_category ADD CONSTRAINT FK_D71AECDCE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE finance_category ADD CONSTRAINT FK_D71AECDC727ACA70 FOREIGN KEY (parent_id) REFERENCES finance_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE finance_entry ADD CONSTRAINT FK_2B8E898B12469DE2 FOREIGN KEY (category_id) REFERENCES finance_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE finance_entry ADD CONSTRAINT FK_2B8E898B7597D3FE FOREIGN KEY (member_id) REFERENCES band_space_membership (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE finance_entry_split ADD CONSTRAINT FK_CAD2287CBA364942 FOREIGN KEY (entry_id) REFERENCES finance_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE finance_entry_split ADD CONSTRAINT FK_CAD2287C7597D3FE FOREIGN KEY (member_id) REFERENCES band_space_membership (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE finance_category DROP FOREIGN KEY FK_D71AECDCE31C124A');
        $this->addSql('ALTER TABLE finance_category DROP FOREIGN KEY FK_D71AECDC727ACA70');
        $this->addSql('ALTER TABLE finance_entry DROP FOREIGN KEY FK_2B8E898B12469DE2');
        $this->addSql('ALTER TABLE finance_entry DROP FOREIGN KEY FK_2B8E898B7597D3FE');
        $this->addSql('ALTER TABLE finance_entry_split DROP FOREIGN KEY FK_CAD2287CBA364942');
        $this->addSql('ALTER TABLE finance_entry_split DROP FOREIGN KEY FK_CAD2287C7597D3FE');
        $this->addSql('DROP TABLE finance_category');
        $this->addSql('DROP TABLE finance_entry');
        $this->addSql('DROP TABLE finance_entry_split');
    }
}
