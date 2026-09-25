<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chat message can carry an image (#973).
 *
 * No foreign key on purpose: the band space file can be purged from the Files trash while the message
 * stays, and the id outliving it is what lets the bubble say « Image supprimée ».
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260925191105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the image a chat message carries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD image_file_id CHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP image_file_id');
    }
}
