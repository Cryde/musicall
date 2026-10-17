<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017001713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Musician announce languages, every existing announce reads ["fr"] (#1189)';
    }

    public function up(Schema $schema): void
    {
        // Nullable first: an instant ADD ... NOT NULL silently fills existing rows with '', which Doctrine hydrates as null.
        $this->addSql('ALTER TABLE musician_announce ADD languages JSON DEFAULT NULL');
        $this->addSql('UPDATE musician_announce SET languages = \'["fr"]\'');
        $this->addSql('ALTER TABLE musician_announce MODIFY languages JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE musician_announce DROP languages');
    }
}
