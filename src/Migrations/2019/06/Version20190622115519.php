<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190622115519 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE publication_cover (id INT AUTO_INCREMENT NOT NULL, publication_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_size INT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_68D6C04938B217A7 (publication_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE publication_cover ADD CONSTRAINT FK_68D6C04938B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication ADD cover_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779922726E9 FOREIGN KEY (cover_id) REFERENCES publication_cover (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF3C6779922726E9 ON publication (cover_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779922726E9');
        $this->addSql('DROP TABLE publication_cover');
        $this->addSql('DROP INDEX UNIQ_AF3C6779922726E9 ON publication');
        $this->addSql('ALTER TABLE publication DROP cover_id');
    }
}
