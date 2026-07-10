<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Known IP address and related information about it (location of its computer).
 */
#[ORM\Entity]
class IpAddress
{
    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    /**
     * The IP address.
     */
    #[ORM\Column(type: 'string', unique: true)]
    protected string $ipAddress;

    /**
     * The hostname (or domain) associated with this IP address.
     */
    #[ORM\Column(type: 'string')]
    protected string $hostname;

    /**
     * Physical location of the computer with this IP address (e.g., room name).
     */
    #[ORM\Column(type: 'string')]
    protected string $location;

    /**
     * The type of seating arrangement in the room (e.g., "5x3" for 5 rows and 3 columns).
     */
    #[ORM\Column(type: 'string')]
    protected string $seatingType;
    /**
     * Index of the row in the room (seating arrangement in labs), zero = unknown.
     */
    #[ORM\Column(type: 'integer')]
    protected int $row = 0;

    /**
     * Index of the column in the room (seating arrangement in labs), zero = unknown.
     */
    #[ORM\Column(type: 'integer')]
    protected int $column = 0;

    public function __construct(
        string $ipAddress,
        string $hostname = '',
        string $location = '',
        string $seatingType = '',
        int $row = 0,
        int $column = 0
    ) {
        $this->ipAddress = $ipAddress;
        $this->hostname = $hostname;
        $this->location = $location;
        $this->seatingType = $seatingType;
        $this->row = $row;
        $this->column = $column;
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getSeatingType(): string
    {
        return $this->seatingType;
    }

    public function getRow(): int
    {
        return $this->row;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Return true if this IP address has seating information (row and column) associated with it.
     */
    public function hasSeating(): bool
    {
        return $this->seatingType !== '' && $this->row !== 0 && $this->column !== 0;
    }
}
