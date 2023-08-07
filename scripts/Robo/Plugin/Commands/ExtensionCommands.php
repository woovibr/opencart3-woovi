<?php

namespace Scripts\Robo\Plugin\Commands;

use Scripts\Robo\BaseTasks;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Finder\{Finder, SplFileInfo};

/**
 * Commands for managing the extension installation.
 */
class ExtensionCommands extends BaseTasks
{
    /**
     * Symlink the Woovi extension directory into the OpenCart code base.
     */
    public function extensionLink()
    {
        $this->dotenv->required(["EXTENSION_PATH", "OPENCART_PATH"])->notEmpty();
        
        $baseDirectory = getenv("EXTENSION_PATH") . "/src";
        $opencartPath = getenv("OPENCART_PATH");

        $finder = (new Finder())
            ->in([$baseDirectory . "/admin", $baseDirectory . "/catalog", $baseDirectory . "/system"]);

        $entries = iterator_to_array($finder);

        $filesystemStack = $this->taskFilesystemStack();

        foreach ($entries as $fromPath) {
            $toPath = str_replace($baseDirectory, $opencartPath, $fromPath);

            if (file_exists($fromPath)) continue;
            
            $filesystemStack->symlink($fromPath, $toPath);
        }

        $filesystemStack->run();
    }

    /**
     * Enable extension on OpenCart.
     */
    public function extensionEnable()
    {
        $this->dotenv->required(["APP_PORT", "OPENCART_PATH"])->notEmpty();

        // Load OpenCart
        chdir(getenv("OPENCART_PATH") . "/admin");

        $_SERVER["SERVER_PORT"] = getenv("APP_PORT");
        $_SERVER["SERVER_PROTOCOL"] = "CLI";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

        ob_start();
        $application_config = "admin";
        require_once("config.php");
        require_once(DIR_SYSTEM . "startup.php");
        require(DIR_SYSTEM . "framework.php");
        ob_get_clean();

        // Login user
        $user = $registry->get("user");
        $session = $registry->get("session");

        $user->login(getenv("OPENCART_USER_NAME"), getenv("OPENCART_USER_PASSWORD"));
        $session->data["user_id"] = $user->getId();
        $session->data["user_token"] = bin2hex(openssl_random_pseudo_bytes(16));

        // Run extension installer
        $request = $registry->get("request");

        $request->get["code"] = "woovi";
        $request->get["extension"] = "woovi";

        $loader = $registry->get("load");

        $loader->controller("extension/extension/payment|install");
    }

    /**
     * Disable extension on OpenCart.
     */
    public function extensionDisable()
    {
        // TODO
    }

    /**
     * Enable an environment for extension.
     *
     * @param "production"|"development"|"staging" $environment Type of environment. Allowed: production, development or staging.
     * @phpstan-param array<array-key, mixed> $opts
     *
     * @option $force Enable even if already enabled.
     */
    public function extensionEnableEnvironment(ConsoleIO $consoleIO, string $environment, array $opts = ["force|f" => false])
    {
        $configPath = getcwd() . "/src/system/config/woovi.";
        $envTemplatePath = $configPath . $environment . ".php";
        $envPath = $configPath . "php";

        if (! file_exists($envTemplatePath)) {
            $consoleIO->error("Environment template path `" . $envTemplatePath . "` does not exist!");
            return;
        }

        $envAlreadyExists = file_exists($envPath);

        $this->taskFilesystemStack()
            ->copy($envTemplatePath, $envPath, $opts["force"])
            ->run();

        if (! $envAlreadyExists || ($envAlreadyExists && $opts["force"])) {
            $consoleIO->success("`" . $environment . "` environment enabled!");
        }
    }

    /**
     * Build an release artifact for extension.
     */
    public function extensionBuild(ConsoleIO $consoleIO)
    {
        $collection = $this->collectionBuilder();

        // Create an archive file using Git that with .gitattributes.
        $createGitArchiveTemporaryFile = $this->taskTmpFile("opencart-woovi-git-archive", ".zip");
        $gitArchivePath = $createGitArchiveTemporaryFile->getPath();

        $gitArchive = $this->taskGitStack()
            ->exec("archive -o " . $gitArchivePath . " HEAD");

        // Create an temporary directory that contains `extracted archive.
        // We use this for adding optimized Composer `vendor` directory.
        $changeToWorkFolder = $this->taskTmpDir("opencart-woovi")
            ->cwd(true);

        $extractGitArchive = $this->taskExtract($gitArchivePath)
            ->to($changeToWorkFolder->getPath());

        // Install Composer production dependencies.
        $composerInstall = $this->taskComposerInstall()
            ->optimizeAutoloader()
            ->noDev()
            ->noAnsi()
            ->noInteraction()
            ->noScripts();

        // Enable production environment using this Robo instance.
        $enableEnvironment = fn () => $this->extensionEnableEnvironment(
            $consoleIO,
            "production",
            ["force" => true]
        );

        // Add tasks to collection.
        $collection->addTaskList([
            $createGitArchiveTemporaryFile,
            $gitArchive,
            $extractGitArchive,
            $changeToWorkFolder,
            $composerInstall,
        ]);

        $collection->addCode($enableEnvironment);

        // Prepare build directory.
        $buildDirectoryPath = dirname(__DIR__, 4) . "/build";

        $prepareBuildDirectory = $this->taskFilesystemStack()
            ->mkdir($buildDirectoryPath);

        $artifactPath = $buildDirectoryPath . "/woovi.ocmod.zip";

        if (file_exists($artifactPath)) {
            $prepareBuildDirectory->remove($artifactPath);
        }

        $collection->addTask($prepareBuildDirectory);

        // Pack files into an artifact file.
        $createArtifact = $this->taskPack($artifactPath);

        // Pack files in temporary work dir.
        $collection->addCode(function ()
            use ($createArtifact, $changeToWorkFolder) {
            $paths = $this->findArtifactIncludedFilePaths(
                $changeToWorkFolder->getPath()
            );

            $createArtifact->add($paths);
        });

        $collection->addTask($createArtifact);

        $collection->run();

        $consoleIO->success("Success! Build file is at " . realpath($artifactPath));
    }

    /**
     * Run extension linter.
     */
    public function extensionLint(): void
    {
        $collection = $this->collectionBuilder();

        // You need delete the stub file if you need regenerate stubs.
        if (! file_exists(__DIR__ . "/../../../../stubs/opencart.php")) {
            $collection->taskExec("robo stubs:generate");
        }

        $collection->taskExec("phpstan analyse");
        $collection->run();
    }

    /**
     * Find paths of files that will be included in artifact.
     *
     * @return array<string, string>
     */
    private function findArtifactIncludedFilePaths(string $temporaryWorkDir): array
    {
        $finder = (new Finder)->files()
            ->in($temporaryWorkDir . "/src")
            ->ignoreDotFiles(false);

        $paths = array_flip(array_map(
            fn (SplFileInfo $file) => $file->getRelativePathname(),
            iterator_to_array($finder)
        ));

        return $paths;
    }
}
