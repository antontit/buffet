<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'placement')]
#[ORM\Index(columns: ['shelf_id'], name: 'idx_placement_shelf')]
#[ORM\Index(columns: ['dish_id'], name: 'idx_placement_dish')]
#[ORM\Index(columns: ['stack_id'], name: 'idx_placement_stack')]
class Placement
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

    #[ORM\Column(type: 'float')]
    private float $x;

    #[ORM\Column(type: 'float')]
    private float $y;

    #[ORM\Column(type: 'float')]
    private float $width;

    #[ORM\Column(type: 'float')]
    private float $height;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stackId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stackIndex = null;

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

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): self
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): self
    {
        $this->y = $y;

        return $this;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getStackId(): ?int
    {
        return $this->stackId;
    }

    public function setStackId(?int $stackId): self
    {
        $this->stackId = $stackId;

        return $this;
    }

    public function getStackIndex(): ?int
    {
        return $this->stackIndex;
    }

    public function setStackIndex(?int $stackIndex): self
    {
        $this->stackIndex = $stackIndex;

        return $this;
    }
}
