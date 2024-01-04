<?php

namespace Scripts\Robo;

use Robo\Tasks;
use Dotenv\Dotenv;

/**
 * Provides necessary structure for commands such as environment variables.
 */
abstract class BaseTasks extends Tasks
{
    /**
     * Manage environment variables.
     * 
     * @var Dotenv
     */
    protected $dotenv;

    /**
     * Create a new `BaseTasks` instance.
     */
    public function __construct()
    {
        $this->stopOnFail();

        $this->dotenv = Dotenv::createMutable(__DIR__ . "/../..");
        $this->dotenv->load();
    }
}
