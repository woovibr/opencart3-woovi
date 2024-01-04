<?php

namespace Scripts\Robo\Plugin\Commands;

use Mockery;
use Scripts\Robo\BaseTasks;
use ControllerExtensionWooviPaymentWooviWebhooks as WooviWebhooks;
use Scripts\OpencartRunner;
use MockPhpStream;
use OpenPix\PhpSdk\Client;
use OpenPix\PhpSdk\Resources\Webhooks;
use ReflectionClass;
use Robo\Symfony\ConsoleIO;

/**
 * Commands for managing webhooks.
 */
class WebhooksCommands extends BaseTasks
{
    /**
     * URI of webhook endpoint.
     */
    public const WEBHOOK_ENDPOINT_URI = "extension/woovi/payment/woovi_webhooks";

    /**
     * The opencart runner used by commands.
     * 
     * @var ?OpencartRunner
     */
    private $opencartRunner = null;

    /**
     * Test "completed charge" webhook event.
     */
    public function webhooksCompleteCharge(int $orderId): void
    {
        $this->makeOpencartRunner();

        $this->opencartRunner->load->model("extension/payment/woovi_order");
        $wooviOrder = $this->opencartRunner->model_extension_payment_woovi_order->getWooviOrderByOpencartOrderId($orderId);

        $this->emitWebhookEvent([
            "event" => WooviWebhooks::OPENPIX_CHARGE_COMPLETED_EVENT,
            "charge" => [
                "correlationID" => $wooviOrder["woovi_correlation_id"],
            ],
        ]);
    }

    /*
     * Test an "opencart-configure" webhook event.
     */
    public function webhooksOpencartConfigure(string $appID): void
    {
        $this->makeOpencartRunner();

        $this->emitWebhookEvent([
            "event" => WooviWebhooks::OPENCART_CONFIGURE_EVENT,
            "appID" => $appID,
        ]);
    }

    /**
     * Set webhook signature validation public key on `vendor`
     * for development only.
     */
    public function webhooksSetPublicKey(string $publicKey = ""): void
    {
        if (empty($publicKey)) {
            $publicKey = $_SERVER["WEBHOOKS_PUBLIC_KEY"] ?? "";
        }
        if (empty($publicKey)) {
            return;
        }

        $classPath = $this->getWebhooksResourceClassPath();

        $this->backupWebhooksResourceClass($classPath);

        $this->taskReplaceInFile($classPath)
            ->regex("/private const VALIDATION_PUBLIC_KEY_BASE64 = \".*\";/")
            ->to("private const VALIDATION_PUBLIC_KEY_BASE64 = \"$publicKey\";")
            ->run();
    }

    /**
     * Reset signature validation on webhooks.
     */
    public function webhooksResetSignatureValidation(ConsoleIO $consoleIO): void
    {
        $classPath = $this->getWebhooksResourceClassPath();
        $backupPath = $this->getWebhooksResourceBackupClassPath($classPath);

        if (! file_exists($backupPath)) {
            $consoleIO->warning("No backup file found at `$backupPath`.");
            return;
        }

        $this->_remove($classPath);
        $this->_rename($backupPath, $classPath);
    }

    /**
     * Completely disable signature validation for webhooks.
     */
    public function webhooksDisableSignatureValidation(): void
    {
        $classPath = $this->getWebhooksResourceClassPath();

        $this->backupWebhooksResourceClass($classPath);

        $validationMethodPattern = '/    public function isWebhookValid\(string \$payload, string \$signature\): bool\s+\{\s+.*?\}/s';

        $mockedValidationMethod = <<<'PHP'
    public function isWebhookValid(string $payload, string $signature): bool
    {
        return true;
    }
PHP;

        $this->taskReplaceInFile($classPath)
            ->regex($validationMethodPattern)
            ->to($mockedValidationMethod)
            ->run();
    }

    /**
     * Get `Webhooks` class path.
     */
    private function getWebhooksResourceClassPath(): string
    {
        $reflectionClass = new ReflectionClass(Webhooks::class);

        return $reflectionClass->getFileName();
    }

    /**
     * Get `Webhooks` backup class path.
     */
    private function getWebhooksResourceBackupClassPath(string $currentClassPath): string
    {
        return $currentClassPath . ".bkp";
    }

    /**
     * Create an backup for `Webhooks` class if needed.
     */
    private function backupWebhooksResourceClass(string $classPath): void
    {
        $backupPath = $this->getWebhooksResourceBackupClassPath($classPath);

        if (! file_exists($backupPath)) {
            $this->taskWriteToFile($backupPath)
                ->textFromFile($classPath)
                ->run();
        }
    }

    /**
     * Emit an webhook with given payload.
     */
    private function emitWebhookEvent(array $payload): void
    {
        $runner = $this->makeOpencartRunner();

        $this->mockSignatureValidation($runner);

        MockPhpStream::register();
        file_put_contents("php://input", json_encode($payload));

        $response = $runner->sendRequest("POST", self::WEBHOOK_ENDPOINT_URI);

        MockPhpStream::restore();

        echo "Response from webhook handler:\n";

        var_dump($response->getOutput());
    }

    /**
     * Get an OpenCart runner.
     */
    private function makeOpencartRunner(): OpencartRunner
    {
        if (! is_null($this->opencartRunner)) {
            return $this->opencartRunner;
        }

        $this->opencartRunner = (new OpencartRunner(
            getenv("OPENCART_PATH"),
            getenv("APP_PORT")
        ))->boot();

        include_once(DIR_APPLICATION . "controller/extension/woovi/payment/woovi_webhooks.php");

        return $this->opencartRunner;
    }

    /**
     * Remove signature validation.
     */
    private function mockSignatureValidation(OpencartRunner $opencartRunner): void
    {
        $mockedSdkClient = Mockery::mock(Client::class);
        $_SERVER["HTTP_X_WEBHOOK_SIGNATURE"] = "signature";

        $mockedSdkClient->shouldReceive("webhooks->isWebhookValid")
            ->andReturn(true);

        $opencartRunner->registry->set("woovi_api_client", $mockedSdkClient);
    }
}
