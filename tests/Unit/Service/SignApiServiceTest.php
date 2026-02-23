<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SignApiServiceTest extends TestCase {
    private IClientService&MockObject $clientService;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private SignApiService $service;

    protected function setUp(): void {
        $this->clientService = $this->createMock(IClientService::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SignApiService(
            $this->clientService,
            $this->config,
            $this->logger,
        );
    }

    // ── getApiUrl() 3-Wege-Fallback ──

    public function testGetApiUrlReturnsAppConfigWhenSet(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('https://custom.signd.it/');

        $this->assertSame('https://custom.signd.it', $this->service->getApiUrl());
    }

    public function testGetApiUrlReturnsEnvVarWhenNoAppConfig(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('');

        putenv('SIGND_BASE_URL=http://localhost:7755/');
        try {
            $this->assertSame('http://localhost:7755', $this->service->getApiUrl());
        } finally {
            putenv('SIGND_BASE_URL');
        }
    }

    public function testGetApiUrlReturnsDefaultWhenNothingConfigured(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('');

        putenv('SIGND_BASE_URL');

        $this->assertSame('https://signd.it', $this->service->getApiUrl());
    }

    // ── apiErrorResponse() ──

    public function testApiErrorResponseForLocalServerException(): void {
        $exception = new LocalServerException();
        $response = SignApiService::apiErrorResponse($exception, 'Something failed');

        $this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Cannot reach signd.it server', $data['error']);
        $this->assertSame('SIGND_UNREACHABLE', $data['errorCode']);
    }

    public function testApiErrorResponseForClientExceptionWithJsonBody(): void {
        $psrResponse = new Response(401, [], json_encode(['error' => 'Invalid API key']));
        $psrRequest = new Request('GET', 'https://signd.it/api/test');
        $exception = new ClientException('Unauthorized', $psrRequest, $psrResponse);

        $response = SignApiService::apiErrorResponse($exception, 'Fallback');

        $this->assertSame(401, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Invalid API key', $data['error']);
        $this->assertSame('SIGND_UNAUTHORIZED', $data['errorCode']);
    }

    public function testApiErrorResponseForClientExceptionWithPlainBody(): void {
        $psrResponse = new Response(400, [], 'Bad Request');
        $psrRequest = new Request('POST', 'https://signd.it/api/test');
        $exception = new ClientException('Error', $psrRequest, $psrResponse);

        $response = SignApiService::apiErrorResponse($exception, 'Fallback');

        $this->assertSame(400, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Bad Request', $data['error']);
        $this->assertSame('SIGND_API_ERROR', $data['errorCode']);
    }

    public function testApiErrorResponseForGenericException(): void {
        $exception = new \RuntimeException('Connection timeout');
        $response = SignApiService::apiErrorResponse($exception, 'Something went wrong', 503);

        $this->assertSame(503, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Something went wrong', $data['error']);
        $this->assertSame('SIGND_UNKNOWN_ERROR', $data['errorCode']);
    }

    // ── login() ohne API-Key ──

    public function testLoginSendsRequestWithoutApiKey(): void {
        // getApiUrl() needs api_url config → return empty to fall through to default
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
                [Application::APP_ID, 'api_key', '', ''],
            ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['apiKey' => 'test-key-123']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/v2/api-login'),
                $this->callback(function (array $options): bool {
                    // Verify no X-API-KEY header is sent
                    $this->assertArrayNotHasKey('X-API-KEY', $options['headers']);
                    // Verify correct payload
                    $body = json_decode($options['body'], true);
                    $this->assertSame('test@example.com', $body['email']);
                    $this->assertSame('secret', $body['password']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);

        $result = $this->service->login('test@example.com', 'secret');
        $this->assertSame('test-key-123', $result['apiKey']);
    }
}
