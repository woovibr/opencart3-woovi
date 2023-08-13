<?php

use Woovi\Opencart\Extension;

// Load Composer Autoloader
require_once __DIR__ . "/../../library/woovi/vendor/autoload.php";

/** @var object{registry: Registry} $this */

$extension = new Extension($this->registry);

// Register dependencies needed for extension on OpenCart registry.
$extension->registerDependencies();

return $extension;
