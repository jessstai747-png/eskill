<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreClient;
use PHPUnit\Framework\TestCase;

class MercadoLivreClientHeaderTokenTest extends TestCase
{
    public function testUsesHeaderTokenWhenEnabledAndNoAccountIdProvided(): void
    {
        $previousEnv = $_ENV;
        $previousServer = $_SERVER;
        $previousGet = $_GET;
        $previousPost = $_POST;

        try {
            putenv('ML_ACCESS_TOKEN');
            unset($_ENV['ML_ACCESS_TOKEN']);

            putenv('ML_ALLOW_TOKEN_HEADER=true');
            $_ENV['ML_ALLOW_TOKEN_HEADER'] = 'true';

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['HTTP_X_ML_ACCESS_TOKEN'] = 'Bearer header-token-123';

            $client = new MercadoLivreClient(null);

            $this->assertSame('header-token-123', $client->getAccessToken());

            $ref = new \ReflectionClass($client);
            $tokenSourceProp = $ref->getProperty('tokenSource');
            $tokenSourceProp->setAccessible(true);
            $this->assertSame('header', $tokenSourceProp->getValue($client));
        } finally {
            $_ENV = $previousEnv;
            $_SERVER = $previousServer;
            $_GET = $previousGet;
            $_POST = $previousPost;

            // Best-effort cleanup of process env
            putenv('ML_ALLOW_TOKEN_HEADER');
            putenv('ML_ACCESS_TOKEN');
        }
    }

    public function testDoesNotUseHeaderTokenWhenDisabled(): void
    {
        $previousEnv = $_ENV;
        $previousServer = $_SERVER;
        $previousGet = $_GET;
        $previousPost = $_POST;

        try {
            putenv('ML_ACCESS_TOKEN');
            unset($_ENV['ML_ACCESS_TOKEN']);

            putenv('ML_ALLOW_TOKEN_HEADER=false');
            $_ENV['ML_ALLOW_TOKEN_HEADER'] = 'false';

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['HTTP_X_ML_ACCESS_TOKEN'] = 'Bearer should-not-be-used';

            $client = new MercadoLivreClient(null);

            $this->assertSame('', $client->getAccessToken());

            $ref = new \ReflectionClass($client);
            $tokenSourceProp = $ref->getProperty('tokenSource');
            $tokenSourceProp->setAccessible(true);
            $this->assertSame('none', $tokenSourceProp->getValue($client));
        } finally {
            $_ENV = $previousEnv;
            $_SERVER = $previousServer;
            $_GET = $previousGet;
            $_POST = $previousPost;

            putenv('ML_ALLOW_TOKEN_HEADER');
            putenv('ML_ACCESS_TOKEN');
        }
    }
}
