<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed dishes if none exist';
    }

    public function up(Schema $schema): void
    {
        $existing = (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM dish');
        $this->skipIf($existing, 'Dishes already exist.');

        $this->addSql(<<<'SQL'
            INSERT INTO dish (name, type, image, width, height, stack_limit) VALUES
                ('Bowl', 'bowl', 'images/bowl.png', 140, 90, 10),
                ('Cup', 'cup', 'images/cup.png', 110, 120, 1),
                ('Dish', 'dish', 'images/dish.png', 160, 100, 10),
                ('Soup', 'soup', 'images/soup.png', 150, 90, 1)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM dish WHERE name IN ('Bowl', 'Cup', 'Dish', 'Soup')
            SQL);
    }
}
