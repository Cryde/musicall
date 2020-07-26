<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200726085331 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX message_participant_unique ON message_participant (thread_id, participant_id)');
        $this->addSql('CREATE UNIQUE INDEX message_thread_meta_unique ON message_thread_meta (thread_id, user_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX message_participant_unique ON message_participant');
        $this->addSql('DROP INDEX message_thread_meta_unique ON message_thread_meta');
    }
}
