<?php

namespace Woovi\Opencart;

/**
 * Logs useful information to a log file. 
 */
class Logger
{
    /**
     * Path to log file.
     */
    private string $logFilePath;

    /**
     * Create a new `Logger` instance.
     */
    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
    }

    /**
     * Append an message with INFO level.
     */
    public function info(string $message, ?string $scope = null): void
    {
        $this->appendMessage($message, "INFO", $scope);
    }

    /**
     * Append an message with DEBUG level.
     */
    public function debug(string $message, ?string $scope = null): void
    {
        $this->appendMessage($message, "DEBUG", $scope);
    }

    /**
     * Append an message with WARNING level.
     */
    public function warning(string $message, ?string $scope = null): void
    {
        $this->appendMessage($message, "WARNING", $scope);
    }

    /**
     * Append an message with ERROR level.
     */
    public function error(string $message, ?string $scope = null): void
    {
        $this->appendMessage($message, "ERROR", $scope);
    }

    /**
     * Append an message with NOTICE level.
     */
    public function notice(string $message, ?string $scope = null): void
    {
        $this->appendMessage($message, "NOTICE", $scope);
    }

    /**
     * Append message to file.
     */
    private function appendMessage(string $message, string $level, ?string $scope = null): void
    {
        $message = $this->makeMessageHeader($level, $scope)
            . $message
            . $this->makeMessageFooter();

        file_put_contents($this->logFilePath, $message, FILE_APPEND);
    }

    /**
     * Make header of log messages.
     */
    private function makeMessageHeader(string $level, ?string $scope = null): string
    {
        return date("[Y-m-d H:i:s]")
            . " [$level]"
            . " woovi"
            . ($scope ? "($scope)" : "")
            . ": ";
    }

    /**
     * Make footer of log messages.
     */
    private function makeMessageFooter(): string
    {
        $footer = " ";

        // Find caller from backtrace.
        // It is taking into account this method and the appendMessage method.
        
        // Not populate the "object" index and omit the "args" index,
        // and thus all the function/method arguments, to save memory.
        $options = false|DEBUG_BACKTRACE_IGNORE_ARGS;
        
        $caller = array_slice(debug_backtrace($options, 3), 2, 1)[0];

        if (! empty($caller["file"]) && ! empty($caller["line"])) {
            $footer .= "(". $caller["file"] . ":" . $caller["line"] . ")";
        }

        return $footer . "\n";
    }
}