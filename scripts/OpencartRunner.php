<?php

namespace Scripts;

use Registry;
use Response;

/**
 * Runs an OpenCart endpoint.
 *
 * @property \Opencart\System\Library\Request $request
 * @property \Opencart\System\Engine\Controller $controller
 * @property Response $response
 * @property \Opencart\System\Engine\Loader $load
 * @property \Opencart\Catalog\Model\Extension\Woovi\Payment\WooviOrder $model_extension_woovi_payment_woovi_order
 */
class OpencartRunner
{
    /**
     * Indicate if OpenCart is already booted.
     */
    private bool $booted = false;

    /**
     * The registry of OpenCart.
     */
    public Registry $registry;

    /**
     * The path of OpenCart installation.
     */
    private string $opencartPath;

    /**
     * The port of application.
     */
    private string $appPort;

    /**
     * Create a new `OpencartRunner` instance.
     */
    public function __construct(string $opencartPath, string $appPort)
    {
        $this->opencartPath = $opencartPath;
        $this->appPort = $appPort;
    }

    /**
     * Boot the OpenCart.
     */
    public function boot(string $area = "catalog"): self
    {
        if ($this->booted) {
            return $this;
        }

        $frontControllerDirectory = $area == "catalog" ? "" : $area;

        chdir($this->opencartPath . "/" . $frontControllerDirectory);

        $_SERVER["SERVER_PORT"] = $this->appPort;
        $_SERVER["SERVER_PROTOCOL"] = "CLI";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

        ob_start();
        $application_config = $area;
        require_once("config.php");
        require_once(DIR_SYSTEM . "startup.php");
        require(DIR_SYSTEM . "framework.php");
        ob_get_clean();

        /** @var Registry $registry */
        $this->registry = $registry;

        $this->booted = true;

        return $this;
    }

    /**
     * Send an request to an endpoint.
     *
     * @param mixed $data
     */
    public function sendRequest(string $method, string $uri, $data = []): Response
    {
        $lowercaseMethod = strtolower($method);

        $this->request->{$lowercaseMethod} = $this->request->{$lowercaseMethod} + $data;
        $this->request->cookie["language"] = "en-gb";
        $this->request->cookie["currency"] = "USD";
        $this->request->server["REQUEST_METHOD"] = $method;
        $this->request->get["route"] = $uri;

        $this->load->controller($uri);

        return $this->response;
    }

    /**
     * Retrieve an service from registry.
     *
     * @return object|null
     */
    public function __get(string $id)
    {
        return $this->registry->get($id);
    }
}
