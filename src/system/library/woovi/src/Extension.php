<?php

namespace Woovi\Opencart;

use Registry;
use Loader;
use Config;
use OpenPix\PhpSdk\Client;

/**
 * Service provider for Woovi Opencart library.
 *
 * @phpstan-type WooviEnvironment array{env?: "production"|"development"|"staging", apiUrl?: string, pluginUrl?: string}
 */
class Extension
{
    /**
     * Registry of OpenCart dependencies.
     * 
     * @var Registry
     */
    private $registry;

    /**
     * Create a new `Extension` instance.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Register services and configuration to OpenCart.
     */
    public function register(): void
    {
        $this->registry->set("woovi_extension", $this);
        $this->registerConfig();
        $this->registerPhpSdkClient();
        $this->registerLogger();
    }

    /**
     * Get environment data.
     *
     * @return WooviEnvironment
     */
    public function getEnvironment(): array
    {
        $env = $this->getConfig()->get("woovi_env");

        if (! is_array($env)) {
            return [];
        }

        return $env;
    }

    /**
     * Register config.
     */
    public function registerConfig(): void
    {
        if (file_exists(DIR_CONFIG . "woovi.php")) {
            $this->getLoader()->config("woovi");
        }
    }

    /**
     * Register PHP-SDK Client service.
     */
    public function registerPhpSdkClient(): void
    {
        if ($this->registry->has("woovi_api_client")) {
            return;
        }

        $this->registry->set("woovi_api_client", $this->makeWooviApiClient());
    }

    /**
     * Register logger service.
     */
    public function registerLogger(): void
    {
        if ($this->registry->has("woovi_logger")) {
            return;
        }

        $logFilePaths = [
            DIR_LOGS . "/error.log",
            DIR_LOGS . "/woovi.log",
        ];

        $this->registry->set("woovi_logger", new Logger($logFilePaths));
    }

    /**
     * Make a new `Client` instance using App ID from settings.
     */
    private function makeWooviApiClient(): Client
    {
        /** @var string $appId */
        $appId = $this->getConfig()->get("payment_woovi_app_id");

        $apiUrl = $this->getEnvironment()["apiUrl"] ?? "https://api.woovi.com";

        return Client::create($appId, $apiUrl);
    }

    /**
     * Get loader from registry.
     */
    private function getLoader(): Loader
    {
        /** @var Loader $loader */
        $loader = $this->registry->get("load");

        return $loader;
    }

    /**
     * Get config from registry.
     */
    private function getConfig(): Config
    {
        /** @var Config $config */
        $config = $this->registry->get("config");

        return $config;
    }
}
