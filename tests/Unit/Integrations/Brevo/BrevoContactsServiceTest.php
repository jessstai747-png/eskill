<?php

namespace Tests\Unit\Integrations\Brevo;

use App\Services\Integrations\Brevo\BrevoApiException;
use App\Services\Integrations\Brevo\BrevoContactsService;
use PHPUnit\Framework\TestCase;

class BrevoContactsServiceTest extends TestCase
{
    public function testCreateContactValidatesEmail(): void
    {
        $svc = new BrevoContactsService();

        $this->expectException(BrevoApiException::class);
        $svc->createContact(['email' => 'not-an-email']);
    }
}

