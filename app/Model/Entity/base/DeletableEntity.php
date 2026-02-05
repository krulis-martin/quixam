<?php

declare(strict_types=1);

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait DeletableEntity
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     */
    protected $deletedAt = null;

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    // this is for special operations only, the deletedAt value is normally set by SoftDelete plugin...
    public function setDeleted()
    {
        $this->deletedAt = new DateTime();
    }
}
