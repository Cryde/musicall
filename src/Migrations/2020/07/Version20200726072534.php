<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\IdToUuidMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200726072534 extends IdToUuidMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->migrate('message_participant');
        $this->addSql('ALTER TABLE message_thread_meta DROP INDEX message_thread_meta_unique');
        $this->addSql('ALTER TABLE message_participant DROP INDEX message_participant_unique');
    }
}
