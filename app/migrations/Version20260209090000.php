<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make dish.image non-nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish ALTER COLUMN image SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish ALTER COLUMN image DROP NOT NULL');
    }
}
