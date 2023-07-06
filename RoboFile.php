<?php

use Robo\Tasks;
use Dotenv\Dotenv;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see https://robo.li/
 */
class RoboFile extends Tasks
{
    /**
     * Manage environment variables.
     */
    private Dotenv $dotenv;

    /**
     * Create a new `RoboFile` instance.
     */
    public function __construct()
    {
        $this->stopOnFail();

        $this->dotenv = Dotenv::createImmutable(__DIR__);
        $this->dotenv->load();
    }

    /**
     * Symlink the Woovi extension directory into the OpenCart `extension` directory.
     */
    public function extensionLink()
    {
        $this->dotenv->required(["EXTENSION_PATH", "OPENCART_PATH"])->notEmpty();
        $this->_symlink(getenv("EXTENSION_PATH") . "/extension/woovi", getenv("OPENCART_PATH") . "/extension/woovi");
    }

    /**
     * Enable extension on OpenCart.
     */
    public function extensionEnable()
    {
        $this->dotenv->required(["APP_PORT", "OPENCART_PATH"])->notEmpty();

        // Load OpenCart
        chdir(getenv("OPENCART_PATH") . "/administration");

        $_SERVER["SERVER_PORT"] = getenv("APP_PORT");
        $_SERVER["SERVER_PROTOCOL"] = "CLI";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

        ob_start();
        require_once("index.php");
        ob_get_clean();

        // Login user
        $user = $registry->get("user");
        $session = $registry->get("session");

        $user->login(getenv("OPENCART_USER_NAME"), getenv("OPENCART_USER_PASSWORD"));
        $session->data["user_id"] = $user->getId();
        $session->data["user_token"] = bin2hex(openssl_random_pseudo_bytes(16));

        $extensionSettingModel = $registry->get("model_setting_extension");
        $settingModel = $registry->get("model_setting_setting");

        // Add extension install
        if (empty($extensionSettingModel->getInstallByCode("woovi"))) {
            $installManifest = json_decode(file_get_contents(getenv("OPENCART_PATH") . "/extension/woovi/install.json"), true);

            $extensionInstallId = $extensionSettingModel->addInstall([
                "extension_id"          => 0,
                "extension_download_id" => 0,
            ] + $installManifest);

            $extensionSettingModel->editStatus($extensionInstallId, true);

            $settingModel->editSetting("payment_woovi", ["payment_woovi_status" => true]);
        }

        $extensionInstallId = $extensionSettingModel->getInstallByCode("woovi")["extension_install_id"];

        // Add extension path
        $paths = $extensionSettingModel->getPaths($adminControllerPath = "woovi/admin/controller/payment/woovi.php");

        if (count($paths) == 0) {
            $extensionSettingModel->addPath($extensionInstallId, $adminControllerPath);
        }

        // Run extension installer
        $request = $registry->get("request");

        $request->get["code"] = "woovi";
        $request->get["extension"] = "woovi";

        $loader = $registry->get("load");

        $loader->controller("extension/payment|install");
    }

    /**
     * Disable extension on OpenCart.
     */
    public function extensionDisable()
    {
        // TODO
    }

    /**
     * Build an release file for extension.
     */
    public function extensionBuild()
    {
        // TODO
    }

    /**
     * Setup OpenCart by running its installation script which creates our database.
     */
    public function opencartSetup()
    {
        $installScriptPath = getenv("OPENCART_PATH") . "/install/cli_install.php";

        if (!file_exists($installScriptPath)) return;

        $this->dotenv->required([
            "OPENCART_PATH",
            "OPENCART_USER_EMAIL",
            "OPENCART_USER_PASSWORD",
            "APP_URL",
            "MYSQL_HOST",
            "MYSQL_USER",
            "MYSQL_PASSWORD",
            "MYSQL_DATABASE",
            "MYSQL_PORT"
        ])->notEmpty();

        $this->taskExec("php")
            ->arg($installScriptPath)
            ->arg("install")
            ->options([
                "username" => getenv("OPENCART_USER_NAME"),
                "password" => getenv("OPENCART_USER_PASSWORD"),
                "email" => getenv("OPENCART_USER_EMAIL"),
                "http_server" => getenv("APP_URL"),
                "db_driver" => "mysqli",
                "db_hostname" => getenv("MYSQL_HOST"),
                "db_username" => getenv("MYSQL_USER"),
                "db_password" => getenv("MYSQL_PASSWORD"),
                "db_database" => getenv("MYSQL_DATABASE"),
                "db_port" => getenv("MYSQL_PORT"),
                "db_prefix" => "oc_",
            ])
            ->run();
    }

    /**
     * Run the PHP built-in server for OpenCart.
     */
    public function opencartServe()
    {
        $this->dotenv->required(["OPENCART_PATH", "APP_PORT"])->notEmpty();
        $this->taskServer(getenv("APP_PORT"))
            ->host("0.0.0.0")
            ->dir(getenv("OPENCART_PATH"))
            ->run();
    }

    /**
     * Download OpenCart with version from .env file.
     */
    public function opencartDownload()
    {
        // TODO
    }

    /**
     * Fixes OpenCart warnings like deleting the install directory and renaming the admin directory.
     */
    public function opencartFix()
    {
        $this->dotenv->required([
            "OPENCART_PATH",
            "OPENCART_STORAGE_PATH",
            "APP_URL",
            "EXTENSION_PATH",
        ])->notEmpty();

        // Remove install directory.
        if (is_dir($installDirectoryPath = getenv("OPENCART_PATH") . "/install")) {
            $this->_deleteDir($installDirectoryPath);
        }

        // Rename admin folder to administration.
        if (is_dir($adminPath = getenv("OPENCART_PATH") . "/admin")) {
            $this->_copyDir(
                $adminPath,
                getenv("OPENCART_PATH") . "/administration"
            );
            $this->_deleteDir($adminPath);
        }

        // Move storage folder out of public folder.
        $this->_mkdir(getenv("OPENCART_STORAGE_PATH"));

        if (is_dir($oldStoragePath = getenv("OPENCART_PATH") . "/system/storage")) {
            $this->_copyDir($oldStoragePath, getenv("OPENCART_STORAGE_PATH"));
            $this->_deleteDir($oldStoragePath);
        }

        $this->_chmod(getenv("OPENCART_STORAGE_PATH"), 0777, 0000, true);

        // Fix storage path on config files.
        $this->taskReplaceInFile(getenv("OPENCART_PATH") . "/config.php")
            ->regex("~define\('DIR_STORAGE', .*\);~")
            ->to("define('DIR_STORAGE', '" . getenv("OPENCART_STORAGE_PATH") . "');")
            ->run();

        $this->taskReplaceInFile(getenv("OPENCART_PATH") . "/administration/config.php")
            ->regex("~define\('DIR_STORAGE', .*\);~")
            ->to("define('DIR_STORAGE', '" . getenv("OPENCART_STORAGE_PATH") . "');")
            ->run();

        $this->taskReplaceInFile(getenv("OPENCART_PATH") . "/administration/config.php")
            ->from("admin/")
            ->to("administration/")
            ->run();

        echo "Current admin URL: <" . getenv("APP_URL") . "administration/>\n";
    }
}
