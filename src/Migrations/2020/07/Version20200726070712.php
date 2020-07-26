<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\IdToUuidMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200726070712 extends IdToUuidMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function postUp(Schema $schema): void
    {
        $this->migrate('attribute_instrument');
        $this->migrate('attribute_style');
    }
}
