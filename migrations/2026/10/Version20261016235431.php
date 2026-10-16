<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016235431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publication translation group, at most one publication per group and language (#1187)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication ADD translation_group CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX publication_translation_group_language_unique ON publication (translation_group, language)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX publication_translation_group_language_unique ON publication');
        $this->addSql('ALTER TABLE publication DROP translation_group');
    }
}
