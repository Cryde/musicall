<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128094222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1453: a service stores the year its experience started instead of a count of years';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD experience_start_year SMALLINT DEFAULT NULL');
        // Each count was typed the year its service was created, which stays true if this runs after New Year.
        $this->addSql('UPDATE service SET experience_start_year = YEAR(creation_datetime) - years_of_experience WHERE years_of_experience IS NOT NULL');
        $this->addSql('ALTER TABLE service DROP years_of_experience');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD years_of_experience INT DEFAULT NULL');
        $this->addSql('UPDATE service SET years_of_experience = YEAR(CURDATE()) - experience_start_year WHERE experience_start_year IS NOT NULL');
        $this->addSql('ALTER TABLE service DROP experience_start_year');
    }
}
