<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\Answer;
use App\Model\Entity\IpAddress;
use App\Model\Entity\TestTerm;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ParameterType;

/**
 * @extends BaseRepository<IpAddress>
 */
class IpAddresses extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, IpAddress::class);
    }

    /**
     * Returns all known IP addresses indexed by the IP address string representation.
     * @return IpAddress[]
     */
    public function getAllIpAddresses(): array
    {
        $ipAddresses = $this->findAll();
        $result = [];
        foreach ($ipAddresses as $ipAddress) {
            $result[$ipAddress->getIpAddress()] = $ipAddress;
        }
        return $result;
    }

    /**
     * Returns IP addresses used by the enrolled users in a particular test term.
     * @return array[] indexed by enrolled user ID, each value is an associative array IP string => IpAddress entity
     *                 (if the address is not known, the value is null)
     */
    public function getIpAddressesUsedInTerm(TestTerm $term): array
    {
        $ips = $this->getAllIpAddresses();
        $stmt = $this->em->getConnection()->prepare(
            "SELECT q.enrolled_user_id, an.ip_address FROM `answer` AS an
            JOIN question AS q ON an.question_id = q.id JOIN enrolled_user AS eu ON q.enrolled_user_id = eu.id
            WHERE eu.test_id = :termId AND an.ip_address != ''
            GROUP BY q.enrolled_user_id, an.ip_address
            ORDER BY q.enrolled_user_id"
        );
        $stmt->bindValue('termId', $term->getId(), ParameterType::STRING);
        $res = $stmt->executeQuery();

        $result = [];
        while ($row = $res->fetchAssociative()) {
            $result[$row['enrolled_user_id']] = $result[$row['enrolled_user_id']] ?? [];
            $result[$row['enrolled_user_id']][$row['ip_address']] = $ips[$row['ip_address']] ?? null;
        }
        return $result;
    }
}
