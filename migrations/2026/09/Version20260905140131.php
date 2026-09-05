<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * In app feedback: a short report carrying the page and the module it was sent from.
 */
final class Version20260905140131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the feedback table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feedback (id CHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, module VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, email VARCHAR(255) DEFAULT NULL, page_url VARCHAR(255) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, creation_datetime DATETIME NOT NULL, user_id CHAR(36) DEFAULT NULL, band_space_id CHAR(36) DEFAULT NULL, INDEX IDX_D2294458A76ED395 (user_id), INDEX IDX_D2294458E31C124A (band_space_id), INDEX idx_feedback_triage (status, creation_datetime), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458A76ED395');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458E31C124A');
        $this->addSql('DROP TABLE feedback');
    }
}
