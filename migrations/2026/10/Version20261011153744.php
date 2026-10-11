<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011153744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Band space opened items: each member\'s own history for the palette\'s "opened by me" order (#1047)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_opened_item (id CHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, resource_id CHAR(36) NOT NULL, opened_datetime DATETIME NOT NULL, membership_id CHAR(36) NOT NULL, INDEX idx_band_space_opened_item_latest (membership_id, opened_datetime), UNIQUE INDEX unq_band_space_opened_item (membership_id, type, resource_id), INDEX IDX_781725521FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_opened_item ADD CONSTRAINT FK_781725521FB354CD FOREIGN KEY (membership_id) REFERENCES band_space_membership (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_opened_item DROP FOREIGN KEY FK_781725521FB354CD');
        $this->addSql('DROP TABLE band_space_opened_item');
    }
}
