<?php

namespace Scripts\Robo\Plugin\Commands;

use Scripts\Robo\BaseTasks;
use StubsGenerator\{StubsGenerator, StubsFinder};

/**
 * Commands for managing stubs.
 */
class StubsCommands extends BaseTasks
{
    /**
     * Generate OpenCart stubs.
     *
     * This is needed for linting and intellisense.
     */
    public function stubsGenerate(): void
    {
        $this->dotenv->required(["OPENCART_PATH"])->notEmpty();

        $opencartPath = getenv("OPENCART_PATH");

        $generator = new StubsGenerator();

        $finder = StubsFinder::create()->in($opencartPath);

        $result = $generator->generate($finder)->prettyPrint();

        $this->_mkdir("stubs");
        $this->taskWriteToFile(__DIR__ . "/../../../../stubs/opencart.php")
            ->text($result)
            ->run();
    }
}