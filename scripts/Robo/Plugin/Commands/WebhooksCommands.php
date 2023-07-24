<?php

namespace Scripts\Robo\Plugin\Commands;

use Mockery;
use Scripts\Robo\BaseTasks;
use Opencart\Catalog\Controller\Extension\Woovi\Payment\WooviWebhooks;
use Scripts\OpencartRunner;
use MockPhpStream;
use OpenPix\PhpSdk\Client;

/**
 * Commands for managing webhooks.
 */
class WebhooksCommands extends BaseTasks
{
    /**
     * The opencart runner used by commands.
     */
    private ?OpencartRunner $opencartRunner = null;

    /**
     * Test "completed charge" webhook event.
     */
    public function webhooksCompleteCharge(int $orderId): void
    {
        $this->makeOpencartRunner();

        $this->opencartRunner->load->model("extension/woovi/payment/woovi_order");
        $wooviOrder = $this->opencartRunner->model_extension_woovi_payment_woovi_order->getWooviOrderByOpencartOrderId($orderId);

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
     * Emit an webhook with given payload.
     */
    private function emitWebhookEvent(array $payload): void
    {
        $runner = $this->makeOpencartRunner();

        $this->mockSignatureValidation($runner);

        MockPhpStream::register();
        file_put_contents("php://input", json_encode($payload));

        $response = $runner->sendRequest("POST", "extension/woovi/payment/woovi_webhooks|callback");

        MockPhpStream::restore();

        echo "Response from webhook handler:\n";        

        var_dump($response->getOutput());
    }

    /**
     * Get an OpenCart runner.
     */
    private function makeOpencartRunner(): OpencartRunner
    {
        if (! is_null($this->opencartRunner)) return $this->opencartRunner;

        return $this->opencartRunner = (new OpencartRunner(
            getenv("OPENCART_PATH"),
            getenv("APP_PORT")
        ))->boot();
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