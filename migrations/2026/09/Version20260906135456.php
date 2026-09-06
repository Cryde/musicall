<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906135456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add band_space_agenda_feed_token, the secret half of a member iCal subscription URL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_agenda_feed_token (id CHAR(36) NOT NULL, membership_id CHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, creation_datetime DATETIME NOT NULL, last_access_datetime DATETIME DEFAULT NULL, access_count INT NOT NULL, UNIQUE INDEX UNIQ_CAA3CA65B3BC57DA (token_hash), UNIQUE INDEX UNIQ_CAA3CA651FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_agenda_feed_token ADD CONSTRAINT FK_CAA3CA651FB354CD FOREIGN KEY (membership_id) REFERENCES band_space_membership (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_agenda_feed_token DROP FOREIGN KEY FK_CAA3CA651FB354CD');
        $this->addSql('DROP TABLE band_space_agenda_feed_token');
    }
}
