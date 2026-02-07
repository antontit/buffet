<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207155408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GiST exclusion constraint for placement collisions (PostgreSQL only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->addSql(<<<'SQL'
            ALTER TABLE placement
            ADD CONSTRAINT placement_no_overlap
            EXCLUDE USING gist (
                shelf_id WITH =,
                (COALESCE(stack_id, id)) WITH <>,
                (box(point(x, y), point(x + width, y + height))) WITH &&
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE placement DROP CONSTRAINT IF EXISTS placement_no_overlap');
    }
}
