<?php

use Woovi\Opencart\Extension;

/**
 * Load the Woovi library.
 */
class Woovi
{
    /**
     * OpenCart registry.
     */
    private Registry $registry;

    /**
     * Load the Woovi library.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;

        $this->loadLibrary();
    }

    /**
     * Load the library.
     */
    public function loadLibrary(): void
    {
        require_once __DIR__ . "/woovi/vendor/autoload.php";

        $extension = new Extension($this->registry);

        // Register dependencies and configuration needed for extension on OpenCart.
        $extension->register();
    }
}
