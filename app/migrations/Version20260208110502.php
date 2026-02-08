<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208110502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adjust dish sizes to be proportional to shelves';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE dish
            SET width = CASE name
                WHEN 'Bowl' THEN 140
                WHEN 'Cup' THEN 110
                WHEN 'Dish' THEN 160
                WHEN 'Soup' THEN 150
                ELSE width
            END,
            height = CASE name
                WHEN 'Bowl' THEN 90
                WHEN 'Cup' THEN 120
                WHEN 'Dish' THEN 100
                WHEN 'Soup' THEN 90
                ELSE height
            END
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE dish
            SET width = CASE name
                WHEN 'Bowl' THEN 200
                WHEN 'Cup' THEN 150
                WHEN 'Dish' THEN 250
                WHEN 'Soup' THEN 250
                ELSE width
            END,
            height = CASE name
                WHEN 'Bowl' THEN 200
                WHEN 'Cup' THEN 200
                WHEN 'Dish' THEN 250
                WHEN 'Soup' THEN 200
                ELSE height
            END
            SQL);
    }
}
