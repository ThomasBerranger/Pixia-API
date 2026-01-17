<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Notification;
use App\Enum\NotificationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class NotificationGroupSerializationTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testIndexGroup(): void
    {
        $notification = $this->createTestNotification();

        $json = $this->serializer->serialize($notification, 'json', [
            'groups' => ['notification:index']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(
            ['createdAt', 'title', 'content', 'type', 'read'],
            array_keys($data)
        );
    }

    private function createTestNotification(): Notification
    {
        $notification = new Notification();
        $notification->setTitle('Test Notification');
        $notification->setContent('Test content for notification');
        $notification->setType(NotificationType::BADGE);
        $notification->setRead(false);

        return $notification;
    }
}
