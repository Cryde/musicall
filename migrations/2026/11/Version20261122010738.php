<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M-1415: a service keeps its place among its member\'s services and the date it first went online';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD first_publication_datetime DATETIME DEFAULT NULL, ADD position SMALLINT NOT NULL');
        // Each member's services in the order « Mes services » listed them so far: oldest first.
        $this->addSql('UPDATE service s INNER JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY creation_datetime, id) - 1 AS rank_in_user FROM service) ranked ON ranked.id = s.id SET s.position = ranked.rank_in_user');
        // No date was recorded when a published service went online: its creation stands in.
        $this->addSql("UPDATE service SET first_publication_datetime = creation_datetime WHERE status = 'published'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP first_publication_datetime, DROP position');
    }
}
