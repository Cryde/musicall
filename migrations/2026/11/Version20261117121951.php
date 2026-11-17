<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117121951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Service « Avant, après » sounds (#1410)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_audio (id CHAR(36) NOT NULL, side VARCHAR(10) NOT NULL, title VARCHAR(100) DEFAULT NULL, credit VARCHAR(150) DEFAULT NULL, duration_seconds INT NOT NULL, peaks JSON NOT NULL, size INT DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, service_id CHAR(36) NOT NULL, UNIQUE INDEX unique_service_audio_side (service_id, side), INDEX IDX_B10FDB72ED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE service_audio ADD CONSTRAINT FK_B10FDB72ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_audio DROP FOREIGN KEY FK_B10FDB72ED5CA9E6');
        $this->addSql('DROP TABLE service_audio');
    }
}
