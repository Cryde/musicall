<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514160710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forum_topic_participation table tracking which topics each user posted in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forum_topic_participation (id CHAR(36) NOT NULL, read_datetime DATETIME DEFAULT NULL, removed_datetime DATETIME DEFAULT NULL, creation_datetime DATETIME NOT NULL, user_id CHAR(36) NOT NULL, topic_id CHAR(36) NOT NULL, INDEX IDX_1D541010A76ED395 (user_id), INDEX IDX_1D5410101F55203D (topic_id), UNIQUE INDEX uniq_participation_user_topic (user_id, topic_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE forum_topic_participation ADD CONSTRAINT FK_1D541010A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE forum_topic_participation ADD CONSTRAINT FK_1D5410101F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_topic_participation DROP FOREIGN KEY FK_1D541010A76ED395');
        $this->addSql('ALTER TABLE forum_topic_participation DROP FOREIGN KEY FK_1D5410101F55203D');
        $this->addSql('DROP TABLE forum_topic_participation');
    }
}
