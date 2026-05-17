<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517064755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tag table + map_publication_tag M2M for publication/course tagging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, creation_datetime DATETIME NOT NULL, UNIQUE INDEX uniq_publication_tag_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE map_publication_tag (publication_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_5B35845B38B217A7 (publication_id), INDEX IDX_5B35845BBAD26311 (tag_id), PRIMARY KEY (publication_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE map_publication_tag ADD CONSTRAINT FK_5B35845B38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE map_publication_tag ADD CONSTRAINT FK_5B35845BBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE map_publication_tag DROP FOREIGN KEY FK_5B35845B38B217A7');
        $this->addSql('ALTER TABLE map_publication_tag DROP FOREIGN KEY FK_5B35845BBAD26311');
        $this->addSql('DROP TABLE map_publication_tag');
        $this->addSql('DROP TABLE tag');
    }
}
