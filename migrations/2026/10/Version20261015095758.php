<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015095758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publication language: every existing text reads fr, a video has none (#1186)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication ADD language VARCHAR(10) DEFAULT NULL');
        // Anything that is not a video reads as text, as getTypeLabel() does.
        $this->addSql('UPDATE publication SET language = \'fr\' WHERE type IS NULL OR type <> 2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication DROP language');
    }
}
