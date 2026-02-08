<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208171000 extends AbstractMigration
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
            INSERT INTO shelf (name, width, height, x, y) VALUES
                ('Shelf 1', 964, 155, 88, 76),
                ('Shelf 2', 964, 179, 88, 250),
                ('Shelf 3', 964, 165, 88, 451)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM shelf
            WHERE name IN ('Shelf 1', 'Shelf 2', 'Shelf 3')
            SQL);
    }
}
