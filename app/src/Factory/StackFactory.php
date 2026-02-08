<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Dish;
use App\Entity\Stack;
use App\Entity\Shelf;

final class StackFactory
{
    public function create(int $x, int $y, Shelf $shelf, Dish $dish): Stack
    {
        $stack = new Stack();
        $stack->setShelf($shelf);
        $stack->setDish($dish);
        $stack->setX($x);
        $stack->setY($y);
        $stack->setWidth($dish->getWidth());
        $stack->setHeight($dish->getHeight());
        $stack->setCount(1);

        return $stack;
    }
}
