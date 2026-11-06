<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The bands a member plays in, on the musician profile (#1313): JSON, as the teacher's songs.
 * Added nullable, filled, then required, so the existing profiles start with an empty list.
 */
final class Version20261107100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bands to user_musician_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_musician_profile ADD bands JSON DEFAULT NULL');
        $this->addSql("UPDATE user_musician_profile SET bands = '[]'");
        $this->addSql('ALTER TABLE user_musician_profile CHANGE bands bands JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_musician_profile DROP bands');
    }
}
