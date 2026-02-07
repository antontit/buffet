<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207143135 extends AbstractMigration
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
            INSERT INTO dish (name, type, image, width, height, is_stacked) VALUES
                ('Bowl', 'bowl', 'images/bowl.png', 200, 200, FALSE),
                ('Cup', 'cup', 'images/cup.png', 150, 200, FALSE),
                ('Dish', 'dish', 'images/dish.png', 250, 250, FALSE),
                ('Soup', 'soup', 'images/soup.png', 250, 200, FALSE)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM dish WHERE name IN ('Bowl', 'Cup', 'Dish', 'Soup')
            SQL);
    }
}
