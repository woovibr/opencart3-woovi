<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Startup;

use Opencart\System\Engine\Controller;
use OpenPix\PhpSdk\Client;

/**
 * OpenCart start-up controller for loading Woovi PHP-SDK.
 */
class Api extends Controller
{
    /**
     * Register Woovi PHP-SDK Client on OpenCart registry.
     */
    public function index(): void
    {
        // Apply singleton.
        if ($this->registry->has("woovi_php_sdk")) return;

        $this->loadComposerAutoloader();
        $this->register();
    }

    /**
     * Add Client to registry.
     */
    private function register(): void
    {
        $this->registry->set("woovi_php_sdk", $this->makeClient());
    }

    /**
     * Make a new `Client` instance using App ID from settings.
     */
    private function makeClient(): Client
    {
        return Client::create(
            $this->config->get("payment_woovi_app_id"),

            // Use Woovi API.
            "https://api.woovi.com"
        );
    }

    /**
     * Load Composer autoloader for loading PHP-SDK.
     */
    private function loadComposerAutoloader(): void
    {
        require_once __DIR__ . "/../../../system/library/woovi/vendor/autoload.php";
    }
}