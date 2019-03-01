<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181203071149 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE publication_sub_category (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_FD30EE5D989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication (id INT AUTO_INCREMENT NOT NULL, sub_category_id INT NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, category SMALLINT NOT NULL, slug VARCHAR(255) NOT NULL, short_description LONGTEXT NOT NULL, content LONGTEXT NOT NULL, creation_datetime DATETIME NOT NULL, edition_datetime DATETIME NOT NULL, publication_datetime DATETIME DEFAULT NULL, status SMALLINT NOT NULL, UNIQUE INDEX UNIQ_AF3C6779989D9B62 (slug), INDEX IDX_AF3C6779F7BFE87C (sub_category_id), INDEX IDX_AF3C6779F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES publication_sub_category (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779F7BFE87C');
        $this->addSql('DROP TABLE publication_sub_category');
        $this->addSql('DROP TABLE publication');
    }
}
