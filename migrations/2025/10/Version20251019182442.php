<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019182442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE publication_featured DROP FOREIGN KEY `FK_1374AC8538B217A7`;');
        $this->addSql('ALTER TABLE publication_featured DROP FOREIGN KEY `FK_1374AC85922726E9`;');
        $this->addSql('ALTER TABLE publication_featured_image DROP FOREIGN KEY `FK_4E9235DDD4655A88`;');
        $this->addSql('DROP TABLE publication_featured;');
        $this->addSql('DROP TABLE publication_featured_image;');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE publication_featured (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, creation_datetime DATETIME NOT NULL, level SMALLINT NOT NULL, status SMALLINT NOT NULL, publication_datetime DATETIME DEFAULT NULL, options JSON DEFAULT NULL, publication_id INT NOT NULL, cover_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1374AC85922726E9 (cover_id), INDEX IDX_1374AC8538B217A7 (publication_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE publication_featured_image (id INT AUTO_INCREMENT NOT NULL, image_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, image_size INT NOT NULL, updated_at DATETIME NOT NULL, publication_featured_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4E9235DDD4655A88 (publication_featured_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE publication_featured ADD CONSTRAINT `FK_1374AC8538B217A7` FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication_featured ADD CONSTRAINT `FK_1374AC85922726E9` FOREIGN KEY (cover_id) REFERENCES publication_featured_image (id)');
        $this->addSql('ALTER TABLE publication_featured_image ADD CONSTRAINT `FK_4E9235DDD4655A88` FOREIGN KEY (publication_featured_id) REFERENCES publication_featured (id)');
    }
}
