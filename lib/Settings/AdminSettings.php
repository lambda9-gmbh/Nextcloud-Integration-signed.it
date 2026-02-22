<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Settings;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use Psr\Log\LoggerInterface;

class AdminSettings implements ISettings {
    public function __construct(
        private IInitialState $initialState,
        private SignApiService $signApiService,
        private LoggerInterface $logger,
    ) {
    }

    public function getForm(): TemplateResponse {
        $apiKey = $this->signApiService->getApiKey();
        $apiKeySet = $apiKey !== '';

        $this->initialState->provideInitialState('api_key_set', $apiKeySet);

        if ($apiKeySet) {
            try {
                $userInfo = $this->signApiService->getUserInfo();
                $this->initialState->provideInitialState('user_info', $userInfo);
                $this->initialState->provideInitialState('api_key_valid', true);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to fetch signd user info', ['exception' => $e]);
                $this->initialState->provideInitialState('user_info', null);
                $this->initialState->provideInitialState('api_key_valid', false);
            }
        }

        return new TemplateResponse(Application::APP_ID, 'settings/admin');
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 10;
    }
}
