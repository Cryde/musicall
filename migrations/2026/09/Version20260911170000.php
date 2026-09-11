<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a composite index on message (thread_id, creation_datetime)';
    }

    /**
     * The sort every thread read runs, unindexed since the table was created in 2023.
     *
     * `message` has carried only the two single column foreign key indexes Doctrine generated,
     * `thread_id` and `author_id`, so reading a thread newest first meant filtering on one of them and
     * then sorting the result. Invisible at the sizes we have and linear in thread length, which is
     * what #955 is about now that history is reachable at all.
     *
     * No column is named for the tie break because InnoDB appends the primary key to every secondary
     * index: this is physically `(thread_id, creation_datetime, id)`, so two messages written in the
     * same second still come back in a stable order. That matters more than it sounds, since
     * `creation_datetime` is second granular and same second pairs are ordinary rather than rare.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_message_thread_creation ON message (thread_id, creation_datetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_message_thread_creation ON message');
    }
}
