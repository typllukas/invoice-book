<?php

declare(strict_types=1);

namespace App\Entity\Helper;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

trait CreatedAtLifecycleCallbacksTrait
{
    abstract public function setCreatedAt(DateTimeImmutable $createdAt): self;

    #[ORM\PrePersist]
    public function fillCreatedAtBeforeInsert(): void
    {
        if (!isset($this->createdAt)) {
            $this->setCreatedAt(Clock::get()->now());
        }
    }
}
