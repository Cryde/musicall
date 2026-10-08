<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Message contact origin: the announce or teacher profile a direct message was sent from (#998)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_contact_origin (id CHAR(36) NOT NULL, type VARCHAR(30) NOT NULL, announce_type SMALLINT DEFAULT NULL, instruments JSON NOT NULL, styles JSON NOT NULL, location_name VARCHAR(255) DEFAULT NULL, message_id CHAR(36) NOT NULL, musician_announce_id CHAR(36) DEFAULT NULL, teacher_profile_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_10F435D7537A1329 (message_id), INDEX IDX_10F435D76A4EDF4F (musician_announce_id), INDEX IDX_10F435D746E5B018 (teacher_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT FK_10F435D7537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT FK_10F435D76A4EDF4F FOREIGN KEY (musician_announce_id) REFERENCES musician_announce (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT FK_10F435D746E5B018 FOREIGN KEY (teacher_profile_id) REFERENCES user_teacher_profile (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY FK_10F435D7537A1329');
        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY FK_10F435D76A4EDF4F');
        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY FK_10F435D746E5B018');
        $this->addSql('DROP TABLE message_contact_origin');
    }
}
