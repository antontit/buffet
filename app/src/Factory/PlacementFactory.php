<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Dish;
use App\Entity\Placement;
use App\Entity\Shelf;

final class PlacementFactory
{
    public function create(Shelf $shelf, Dish $dish, int $x, int $y): Placement
    {
        return $this->buildPlacement($shelf, $dish, $x, $y);
    }

    public function createStacked(Shelf $shelf, Dish $dish, Placement $target, int $stackIndex): Placement
    {
        $placement = $this->buildPlacement($shelf, $dish, $target->getX(), $target->getY());
        $placement->setStackId($target->getStackId());
        $placement->setStackIndex($stackIndex);

        return $placement;
    }

    private function buildPlacement(Shelf $shelf, Dish $dish, int $x, int $y): Placement
    {
        $placement = new Placement();
        $placement->setShelf($shelf);
        $placement->setDish($dish);
        $placement->setX($x);
        $placement->setY($y);
        $placement->setWidth($dish->getWidth());
        $placement->setHeight($dish->getHeight());

        return $placement;
    }
}
