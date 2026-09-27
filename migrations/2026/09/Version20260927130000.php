<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The coordinates of the city a member picked for their profile location (#1079). Existing free
 * text locations are geocoded on a best effort basis by `app:user-profile:geocode-locations`.
 */
final class Version20260927130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the coordinates of the user profile location';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_profile ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_profile DROP latitude, DROP longitude');
    }
}
