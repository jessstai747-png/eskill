<?php

namespace Tests\Unit\Integrations\Brevo;

use App\Services\Integrations\Brevo\BrevoApiException;
use App\Services\Integrations\Brevo\BrevoListsService;
use PHPUnit\Framework\TestCase;

class BrevoListsServiceTest extends TestCase
{
    public function testCreateListValidatesName(): void
    {
        $svc = new BrevoListsService();

        $this->expectException(BrevoApiException::class);
        $svc->createList('   ');
    }

    public function testGetListValidatesId(): void
    {
        $svc = new BrevoListsService();

        $this->expectException(BrevoApiException::class);
        $svc->getList(0);
    }

    public function testAddContactsValidatesEmails(): void
    {
        $svc = new BrevoListsService();

        $this->expectException(BrevoApiException::class);
        $svc->addContacts(1, ['invalid-email']);
    }
}

