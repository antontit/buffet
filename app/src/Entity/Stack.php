<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StackRepository::class)]
#[ORM\Table(name: 'stack')]
#[ORM\Index(columns: ['shelf_id'], name: 'idx_stack_shelf')]
#[ORM\Index(columns: ['dish_id'], name: 'idx_stack_dish')]
class Stack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(name: 'shelf_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Shelf $shelf;

    #[ORM\ManyToOne(targetEntity: Dish::class)]
    #[ORM\JoinColumn(name: 'dish_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Dish $dish;

    #[ORM\Column(type: 'integer')]
    private int $x;

    #[ORM\Column(type: 'integer')]
    private int $y;

    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $count = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShelf(): Shelf
    {
        return $this->shelf;
    }

    public function setShelf(Shelf $shelf): self
    {
        $this->shelf = $shelf;

        return $this;
    }

    public function getDish(): Dish
    {
        return $this->dish;
    }

    public function setDish(Dish $dish): self
    {
        $this->dish = $dish;

        return $this;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function setX(int $x): self
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function setY(int $y): self
    {
        $this->y = $y;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }
}
