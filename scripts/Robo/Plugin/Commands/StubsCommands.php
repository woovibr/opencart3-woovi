<?php

namespace Scripts\Robo\Plugin\Commands;

use Robo\Symfony\ConsoleIO;
use Scripts\Robo\BaseTasks;
use StubsGenerator\{StubsGenerator, Finder};
use Symfony\Component\Finder\SplFileInfo;

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
    public function stubsGenerate(ConsoleIO $consoleIO, array $directories): void
    {
        $this->dotenv->required(["OPENCART_PATH"])->notEmpty();
        $opencartPath = getenv("OPENCART_PATH");

        if (empty($directories)) {
            $directories = ["admin", "catalog", "system"];
        }

        foreach ($directories as $directory) {
            $this->generateStubsForDirectory($opencartPath, $directory);
        }
    }

    /**
     * Generate stubs for an single directory.
     */
    private function generateStubsForDirectory(string $opencartPath, string $directory): void
    {
        $generator = new StubsGenerator();

        $finder = Finder::create()->in($opencartPath . "/" . $directory)
            ->notName(["*woovi*"]);

        if ($directory == "catalog") {
            $configPath = $opencartPath . "/config.php";

            $finder->append([
                $configPath => new SplFileInfo(
                    $configPath,
                    "..",
                    "../config.php"
                )
            ]);
        }

        $result = $generator->generate($finder)->prettyPrint();

        $this->_mkdir("stubs");
        $this->taskWriteToFile(__DIR__ . "/../../../../stubs/$directory.php")
            ->text($result)
            ->run();
    }
}
