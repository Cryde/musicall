<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200522060453 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE message_participant (id INT AUTO_INCREMENT NOT NULL, thread_id INT NOT NULL, participant_id INT NOT NULL, creation_datetime DATETIME NOT NULL, INDEX IDX_B7E035E8E2904019 (thread_id), INDEX IDX_B7E035E89D1C3019 (participant_id), UNIQUE INDEX message_participant_unique (thread_id, participant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, author_id INT NOT NULL, thread_id INT NOT NULL, creation_datetime DATETIME NOT NULL, content LONGTEXT NOT NULL, INDEX IDX_B6BD307FF675F31B (author_id), INDEX IDX_B6BD307FE2904019 (thread_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_thread_meta (id INT AUTO_INCREMENT NOT NULL, thread_id INT NOT NULL, user_id INT NOT NULL, creation_datetime DATETIME NOT NULL, is_read TINYINT(1) NOT NULL, is_deleted TINYINT(1) NOT NULL, INDEX IDX_333C5642E2904019 (thread_id), INDEX IDX_333C5642A76ED395 (user_id), UNIQUE INDEX message_thread_meta_unique (thread_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_thread (id INT AUTO_INCREMENT NOT NULL, last_message_id INT NOT NULL, creation_datetime DATETIME DEFAULT NULL, INDEX IDX_607D18CBA0E79C3 (last_message_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE message_participant ADD CONSTRAINT FK_B7E035E8E2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_participant ADD CONSTRAINT FK_B7E035E89D1C3019 FOREIGN KEY (participant_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_thread_meta ADD CONSTRAINT FK_333C5642E2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id)');
        $this->addSql('ALTER TABLE message_thread_meta ADD CONSTRAINT FK_333C5642A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE message_thread ADD CONSTRAINT FK_607D18CBA0E79C3 FOREIGN KEY (last_message_id) REFERENCES message (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE message_thread DROP FOREIGN KEY FK_607D18CBA0E79C3');
        $this->addSql('ALTER TABLE message_participant DROP FOREIGN KEY FK_B7E035E8E2904019');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FE2904019');
        $this->addSql('ALTER TABLE message_thread_meta DROP FOREIGN KEY FK_333C5642E2904019');
        $this->addSql('DROP TABLE message_participant');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE message_thread_meta');
        $this->addSql('DROP TABLE message_thread');
    }
}
