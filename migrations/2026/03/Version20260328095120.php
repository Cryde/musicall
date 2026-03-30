<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328095120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, priority VARCHAR(255) NOT NULL, due_date DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, created_by_id CHAR(36) NOT NULL, category_id CHAR(36) DEFAULT NULL, INDEX IDX_527EDB25E31C124A (band_space_id), INDEX IDX_527EDB25B03A8386 (created_by_id), INDEX IDX_527EDB2512469DE2 (category_id), INDEX idx_task_status (status), INDEX idx_task_priority (priority), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_assignee (task_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, INDEX IDX_3C5D16408DB60186 (task_id), INDEX IDX_3C5D1640A76ED395 (user_id), PRIMARY KEY (task_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_activity (id CHAR(36) NOT NULL, type VARCHAR(30) NOT NULL, payload JSON DEFAULT NULL, creation_datetime DATETIME NOT NULL, task_id CHAR(36) NOT NULL, actor_id CHAR(36) NOT NULL, INDEX IDX_ECB4E3168DB60186 (task_id), INDEX IDX_ECB4E31610DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_category (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, creation_datetime DATETIME NOT NULL, update_datetime DATETIME DEFAULT NULL, band_space_id CHAR(36) NOT NULL, INDEX IDX_468CF38DE31C124A (band_space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_comment (id CHAR(36) NOT NULL, content LONGTEXT NOT NULL, creation_datetime DATETIME NOT NULL, task_id CHAR(36) NOT NULL, author_id CHAR(36) NOT NULL, INDEX IDX_8B9578868DB60186 (task_id), INDEX IDX_8B957886F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2512469DE2 FOREIGN KEY (category_id) REFERENCES task_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D16408DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D1640A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT FK_ECB4E3168DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT FK_ECB4E31610DAF24A FOREIGN KEY (actor_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_category ADD CONSTRAINT FK_468CF38DE31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B9578868DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B957886F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25E31C124A');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2512469DE2');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D16408DB60186');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D1640A76ED395');
        $this->addSql('ALTER TABLE task_activity DROP FOREIGN KEY FK_ECB4E3168DB60186');
        $this->addSql('ALTER TABLE task_activity DROP FOREIGN KEY FK_ECB4E31610DAF24A');
        $this->addSql('ALTER TABLE task_category DROP FOREIGN KEY FK_468CF38DE31C124A');
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B9578868DB60186');
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B957886F675F31B');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignee');
        $this->addSql('DROP TABLE task_activity');
        $this->addSql('DROP TABLE task_category');
        $this->addSql('DROP TABLE task_comment');
    }
}
