<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stack;

final class StackMergeResult
{
    public function __construct(
        private int $movedCount,
        private int $sourceRemaining,
        private Stack $target,
        private ?Stack $source
    ) {
    }

    public function getTarget(): Stack
    {
        return $this->target;
    }

    public function getSource(): ?Stack
    {
        return $this->source;
    }

    public function getMovedCount(): int
    {
        return $this->movedCount;
    }

    public function getSourceRemaining(): int
    {
        return $this->sourceRemaining;
    }
}
