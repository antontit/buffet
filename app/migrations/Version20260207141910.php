<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207141910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed three shelves if none exist';
    }

    public function up(Schema $schema): void
    {
        $isExist = (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM shelf');
        $this->skipIf($isExist, 'Shelves already exist.');

        $this->addSql(<<<'SQL'
            INSERT INTO shelf (name, width, height) VALUES
                ('Shelf 1', 1000, 600),
                ('Shelf 2', 1000, 600),
                ('Shelf 3', 1000, 600)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM shelf
            WHERE name IN ('Shelf 1', 'Shelf 2', 'Shelf 3')
              AND width = 1000
              AND height = 600
            SQL);
    }
}
