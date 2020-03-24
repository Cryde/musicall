<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200324070351 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE gallery ADD cover_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AE5A0E336 FOREIGN KEY (cover_image_id) REFERENCES gallery_image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_472B783AE5A0E336 ON gallery (cover_image_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AE5A0E336');
        $this->addSql('DROP INDEX UNIQ_472B783AE5A0E336 ON gallery');
        $this->addSql('ALTER TABLE gallery DROP cover_image_id');
    }
}
