<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508170911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_file_tag dictionary and band_space_file_to_tag join table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_file_tag (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, color_hex VARCHAR(7) DEFAULT NULL, creation_datetime DATETIME NOT NULL, band_space_id CHAR(36) NOT NULL, INDEX idx_band_space_file_tag_band (band_space_id), UNIQUE INDEX unique_band_space_file_tag_name (band_space_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file_tag ADD CONSTRAINT FK_4DFFFF80E31C124A FOREIGN KEY (band_space_id) REFERENCES band_space (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE band_space_file_to_tag (band_space_file_id CHAR(36) NOT NULL, band_space_file_tag_id CHAR(36) NOT NULL, INDEX IDX_D635D09ECDF230B2 (band_space_file_id), INDEX IDX_D635D09E3952CC02 (band_space_file_tag_id), PRIMARY KEY (band_space_file_id, band_space_file_tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_file_to_tag ADD CONSTRAINT FK_D635D09ECDF230B2 FOREIGN KEY (band_space_file_id) REFERENCES band_space_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE band_space_file_to_tag ADD CONSTRAINT FK_D635D09E3952CC02 FOREIGN KEY (band_space_file_tag_id) REFERENCES band_space_file_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_file_to_tag DROP FOREIGN KEY FK_D635D09ECDF230B2');
        $this->addSql('ALTER TABLE band_space_file_to_tag DROP FOREIGN KEY FK_D635D09E3952CC02');
        $this->addSql('DROP TABLE band_space_file_to_tag');

        $this->addSql('ALTER TABLE band_space_file_tag DROP FOREIGN KEY FK_4DFFFF80E31C124A');
        $this->addSql('DROP TABLE band_space_file_tag');
    }
}
