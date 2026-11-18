<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117200734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1407: a service\'s « Réalisations », with their images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_work (id CHAR(36) NOT NULL, position SMALLINT NOT NULL, title VARCHAR(120) NOT NULL, credit VARCHAR(160) DEFAULT NULL, year SMALLINT DEFAULT NULL, link_url VARCHAR(500) DEFAULT NULL, media_platform VARCHAR(20) DEFAULT NULL, media_url VARCHAR(500) DEFAULT NULL, media_embed_id VARCHAR(255) DEFAULT NULL, media_title VARCHAR(255) DEFAULT NULL, creation_datetime DATETIME NOT NULL, service_id CHAR(36) NOT NULL, INDEX IDX_9D0DDC6CED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_work_image (id CHAR(36) NOT NULL, position SMALLINT NOT NULL, mime_type VARCHAR(191) NOT NULL, size INT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, storage_path VARCHAR(255) DEFAULT NULL, work_id CHAR(36) NOT NULL, INDEX IDX_7B9746C4BB3453DB (work_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE service_work ADD CONSTRAINT FK_9D0DDC6CED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_work_image ADD CONSTRAINT FK_7B9746C4BB3453DB FOREIGN KEY (work_id) REFERENCES service_work (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_work_image DROP FOREIGN KEY FK_7B9746C4BB3453DB');
        $this->addSql('ALTER TABLE service_work DROP FOREIGN KEY FK_9D0DDC6CED5CA9E6');
        $this->addSql('DROP TABLE service_work_image');
        $this->addSql('DROP TABLE service_work');
    }
}
