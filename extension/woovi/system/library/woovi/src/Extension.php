<?php

namespace Woovi\Opencart;

use Opencart\System\Engine\Registry;
use OpenPix\PhpSdk\Client;

/**
 * Service provider for Woovi Opencart library.
 */
class Extension
{
    /**
     * Registry of OpenCart dependencies.
     */
    private Registry $registry;

    /**
     * Create a new `Extension` instance.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;       
    }

    /**
     * Register services to OpenCart registry.
     */
    public function registerDependencies()
    {
        $this->registry->set("woovi_extension", $this);
        $this->registerPhpSdkClient();       
        $this->registerLogger();
    }

    /**
     * Register PHP-SDK Client service.
     */
    private function registerPhpSdkClient(): void
    {
        if ($this->registry->has("woovi_api_client")) return;

        $this->registry->set("woovi_api_client", $this->makeWooviApiClient());
    }

    /**
     * Register logger service.
     */
    private function registerLogger(): void
    {
        if ($this->registry->has("woovi_logger")) return;

        $this->registry->set("woovi_logger", new Logger(DIR_LOGS . "/woovi.log"));
    }

    /**
     * Make a new `Client` instance using App ID from settings.
     */
    private function makeWooviApiClient(): Client
    {
        return Client::create(
            $this->registry->get("config")->get("payment_woovi_app_id"),

            // Use Woovi API.
            "https://api.woovi.com"
        );
    }
}