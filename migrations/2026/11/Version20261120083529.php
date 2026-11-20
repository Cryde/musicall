<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120083529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1408: a message sent from a service page, with the answers of a quote request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_contact_origin ADD service_kind VARCHAR(30) DEFAULT NULL, ADD quote JSON DEFAULT NULL, ADD service_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE message_contact_origin ADD CONSTRAINT FK_10F435D7ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_10F435D7ED5CA9E6 ON message_contact_origin (service_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_contact_origin DROP FOREIGN KEY FK_10F435D7ED5CA9E6');
        $this->addSql('DROP INDEX IDX_10F435D7ED5CA9E6 ON message_contact_origin');
        $this->addSql('ALTER TABLE message_contact_origin DROP service_kind, DROP quote, DROP service_id');
    }
}
