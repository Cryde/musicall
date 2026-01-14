<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114211449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add view tracking to UserProfile and MusicianProfile entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_musician_profile ADD view_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_musician_profile ADD CONSTRAINT FK_C5FA537FF91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5FA537FF91C231C ON user_musician_profile (view_cache_id)');
        $this->addSql('ALTER TABLE user_profile ADD view_cache_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT FK_D95AB405F91C231C FOREIGN KEY (view_cache_id) REFERENCES view_cache (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D95AB405F91C231C ON user_profile (view_cache_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_musician_profile DROP FOREIGN KEY FK_C5FA537FF91C231C');
        $this->addSql('DROP INDEX UNIQ_C5FA537FF91C231C ON user_musician_profile');
        $this->addSql('ALTER TABLE user_musician_profile DROP view_cache_id');
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY FK_D95AB405F91C231C');
        $this->addSql('DROP INDEX UNIQ_D95AB405F91C231C ON user_profile');
        $this->addSql('ALTER TABLE user_profile DROP view_cache_id');
    }
}
