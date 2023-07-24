<?php

namespace Scripts\Robo\Plugin\Commands;

use Scripts\Robo\BaseTasks;

/**
 * Commands for managing OpenCart.
 */
class OpencartCommands extends BaseTasks
{
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