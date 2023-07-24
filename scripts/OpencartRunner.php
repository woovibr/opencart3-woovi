<?php

namespace Scripts;

use Opencart\System\Engine\Registry;
use Opencart\System\Library\Response;

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
     * Create a new `OpencartRunner` instance.
     */
    public function __construct(private string $opencartPath, private string $appPort)
    {}

    /**
     * Boot the OpenCart.
     */
    public function boot(string $area = "catalog"): self
    {
        if ($this->booted) return $this;

        $frontControllerDirectory = $area === "admin"
            ? "administration"
            : ($area === "catalog"
                ? ""
                : $area);

        chdir($this->opencartPath . "/" . $frontControllerDirectory);

        $_SERVER["SERVER_PORT"] = $this->appPort;
        $_SERVER["SERVER_PROTOCOL"] = "CLI";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

        ob_start();
        require_once("index.php");
        ob_get_clean();

        /** @var Registry $registry */
        $this->registry = $registry;

        $this->booted = true;

        return $this;
    }

    /**
     * Send an request to an endpoint.
     */
    public function sendRequest(string $method, string $uri, mixed $data = []): Response
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
     */
    public function __get(string $id): object|null
    {
        return $this->registry->get($id);
    }
}