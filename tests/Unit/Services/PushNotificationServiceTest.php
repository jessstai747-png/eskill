<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PushNotificationService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\PushNotificationService
 */
class PushNotificationServiceTest extends TestCase
{
    public function testSendToUserReturnsErrorWhenNoSubscriptions(): void
    {
        $service = $this->getMockBuilder(PushNotificationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserSubscriptions'])
            ->getMock();

        $service->expects($this->once())
            ->method('getUserSubscriptions')
            ->with(10)
            ->willReturn([]);

        $result = $service->sendToUser(10, ['title' => 'Any', 'body' => 'Message']);

        $this->assertFalse($result['success']);
        $this->assertSame('Usuário não possui subscriptions ativas', $result['error']);
    }

    public function testSendToUserRemovesExpiredSubscription(): void
    {
        $service = $this->getMockBuilder(PushNotificationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserSubscriptions', 'sendNotification', 'removeSubscription'])
            ->getMock();

        $service->expects($this->once())
            ->method('getUserSubscriptions')
            ->with(77)
            ->willReturn([
                [
                    'id' => 5,
                    'endpoint' => 'https://push.example/sub/abc',
                    'p256dh_key' => 'k',
                    'auth_key' => 'a',
                ],
            ]);

        $service->expects($this->once())
            ->method('sendNotification')
            ->willReturn([
                'success' => false,
                'expired' => true,
                'error' => 'Subscription expirada',
            ]);

        $service->expects($this->once())
            ->method('removeSubscription')
            ->with(77, 'https://push.example/sub/abc')
            ->willReturn(['success' => true]);

        $result = $service->sendToUser(77, ['title' => 'x', 'body' => 'y']);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertFalse($result['results'][0]['success']);
    }

    public function testNotifyAlertBuildsPayloadWithTypeAndData(): void
    {
        $service = $this->getMockBuilder(PushNotificationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendToUser'])
            ->getMock();

        $service->expects($this->once())
            ->method('sendToUser')
            ->with(
                15,
                $this->callback(static function (array $payload): bool {
                    return isset($payload['title'], $payload['body'], $payload['data'], $payload['tag'])
                        && $payload['title'] === 'Alerta'
                        && $payload['body'] === 'Mensagem'
                        && isset($payload['data']['type'])
                        && $payload['data']['type'] === 'alert'
                        && isset($payload['data']['url'])
                        && $payload['data']['url'] === '/dashboard'
                        && str_starts_with((string) $payload['tag'], 'alert-');
                })
            )
            ->willReturn(['success' => true]);

        $result = $service->notifyAlert(15, 'Alerta', 'Mensagem', ['url' => '/dashboard']);

        $this->assertTrue($result['success']);
    }

    public function testPublicContractMethodsExist(): void
    {
        $reflection = new ReflectionClass(PushNotificationService::class);
        $methods = [
            'getVapidPublicKey',
            'saveSubscription',
            'removeSubscription',
            'getUserSubscriptions',
            'sendToUser',
            'sendNotification',
            'sendToAll',
            'notifyNewSale',
            'notifyLowStock',
            'notifyAlert',
            'getStats',
            'cleanExpiredSubscriptions',
        ];

        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }
}

