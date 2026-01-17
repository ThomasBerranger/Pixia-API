<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Contribution;
use App\Entity\User;
use App\Enum\ContributionStatus;
use App\Enum\ContributionType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ContributionGroupSerializationTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testShowGroup(): void
    {
        $contribution = $this->createTestContribution();

        $json = $this->serializer->serialize($contribution, 'json', [
            'groups' => ['contribution:show']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(
            ['id', 'title', 'description', 'type', 'status', 'createdAt'],
            array_keys($data)
        );
    }

    private function createTestContribution(): Contribution
    {
        $author = new User();
        $author->setEmail('author@test.com');
        $author->setUsername('author');

        $contribution = new Contribution();
        $contribution->setTitle('Test Contribution');
        $contribution->setDescription('Test Description');
        $contribution->setType(ContributionType::BUG_DETECTED);
        $contribution->setStatus(ContributionStatus::PENDING);
        $contribution->setAuthor($author);

        return $contribution;
    }
}
