<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The musician searches as they are asked (#1075), for the homepage's frequent searches and the
 * admin. Pruned after six months by `app:search-log:prune`.
 *
 * Hand written rather than taken from `doctrine:migrations:diff`, which reads the Foundry story
 * database and carries months of unrelated drift along with it.
 */
final class Version20260927090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the musician search log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE musician_search_log (id CHAR(36) NOT NULL, kind VARCHAR(10) NOT NULL, type SMALLINT DEFAULT NULL, style_ids JSON NOT NULL, location_name VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, ai_query VARCHAR(255) DEFAULT NULL, ai_outcome VARCHAR(10) DEFAULT NULL, first_page_result_count SMALLINT DEFAULT NULL, visitor_hash VARCHAR(64) NOT NULL, authenticated TINYINT NOT NULL, search_datetime DATETIME NOT NULL, instrument_id CHAR(36) DEFAULT NULL, INDEX idx_musician_search_log_kind_datetime (kind, search_datetime), INDEX idx_musician_search_log_datetime (search_datetime), INDEX IDX_58AF9EBDCF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE musician_search_log ADD CONSTRAINT FK_58AF9EBDCF11D9C FOREIGN KEY (instrument_id) REFERENCES attribute_instrument (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE musician_search_log DROP FOREIGN KEY FK_58AF9EBDCF11D9C');
        $this->addSql('DROP TABLE musician_search_log');
    }
}
