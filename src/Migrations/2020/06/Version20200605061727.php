<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200605061727 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_38D96EB85E237E06 ON attribute_instrument (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38D96EB8C021C784 ON attribute_instrument (musician_name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D0855BDC5E237E06 ON attribute_style (name)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_38D96EB85E237E06 ON attribute_instrument');
        $this->addSql('DROP INDEX UNIQ_38D96EB8C021C784 ON attribute_instrument');
        $this->addSql('DROP INDEX UNIQ_D0855BDC5E237E06 ON attribute_style');
    }
}
