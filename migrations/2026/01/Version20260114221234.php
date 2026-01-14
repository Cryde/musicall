<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114221234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add entity_type and entity_id fields to View entity for analytics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE view ADD entity_type VARCHAR(50) DEFAULT NULL, ADD entity_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_view_entity ON view (entity_type, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_view_entity ON view');
        $this->addSql('ALTER TABLE view DROP entity_type, DROP entity_id');
    }
}
