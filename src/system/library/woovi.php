<?php

use Woovi\Opencart\Extension;

/**
 * Load the Woovi library.
 */
class Woovi
{
    /**
     * OpenCart registry.
     * 
     * @var Registry
     */
    private $registry;

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
        $extension = $this->makeExtension();

        // Register dependencies and configuration needed for extension on OpenCart.
        $extension->register();
    }

    /**
     * Create a new `Extension`.
     */
    public function makeExtension(): Extension
    {
        require_once DIR_SYSTEM . "/library/woovi/vendor/autoload.php";

        $extension = new Extension($this->registry);

        return $extension;
    }
}
