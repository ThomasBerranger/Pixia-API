<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\ActivityType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ActivityGroupSerializationTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testIndexGroupExposesExpectedFields(): void
    {
        $activity = $this->createTestActivity();

        $json = $this->serializer->serialize($activity, 'json', [
            'groups' => ['activity:index:super_admin'],
        ]);

        $data = json_decode($json, true);

        self::assertEqualsCanonicalizing(
            ['player', 'type', 'data', 'createdAt'],
            array_keys($data)
        );
    }

    public function testIndexGroupExposesOnlyUsernameOnPlayer(): void
    {
        $activity = $this->createTestActivity();

        $json = $this->serializer->serialize($activity, 'json', [
            'groups' => ['activity:index:super_admin'],
        ]);

        $data = json_decode($json, true);

        self::assertSame(['username'], array_keys($data['player']));
        self::assertSame('player-name', $data['player']['username']);
    }

    public function testIndexGroupNeverLeaksSensitivePlayerData(): void
    {
        $activity = $this->createTestActivity();

        $json = $this->serializer->serialize($activity, 'json', [
            'groups' => ['activity:index:super_admin'],
        ]);

        $data = json_decode($json, true);

        self::assertArrayNotHasKey('email', $data['player']);
        self::assertArrayNotHasKey('password', $data['player']);
        self::assertArrayNotHasKey('roles', $data['player']);
    }

    private function createTestActivity(): Activity
    {
        $player = new User();
        $player->setEmail('player@test.com');
        $player->setUsername('player-name');

        return new Activity($player, ActivityType::PLAYER_TOKENS_CLAIM, ['addedTokens' => 40]);
    }
}
