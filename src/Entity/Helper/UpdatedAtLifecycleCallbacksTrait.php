<?php

declare(strict_types=1);

namespace App\Entity\Helper;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

trait UpdatedAtLifecycleCallbacksTrait
{
    abstract public function setUpdatedAt(DateTimeImmutable $updatedAt): self;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->setUpdatedAt(Clock::get()->now());
    }
}
