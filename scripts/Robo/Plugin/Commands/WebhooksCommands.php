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
     * Emit an "completed charge" webhook event.
     */
    public function webhooksCompleteCharge(int $orderId): void
    {
        $opencartRunner = (new OpencartRunner(
            getenv("OPENCART_PATH"),
            getenv("APP_PORT")
        ))->boot();
        
        $this->mockSignatureValidation($opencartRunner);

        $opencartRunner->load->model("extension/woovi/payment/woovi_order");

        $wooviOrder = $opencartRunner->model_extension_woovi_payment_woovi_order->getWooviOrderByOpencartOrderId($orderId);

        $payload = [
            "event" => WooviWebhooks::OPENPIX_CHARGE_COMPLETED_EVENT,
            "charge" => [
                "correlationID" => $wooviOrder["woovi_correlation_id"],
            ],
        ];

        MockPhpStream::register();
        file_put_contents("php://input", json_encode($payload));

        $response = $opencartRunner->sendRequest("POST", "extension/woovi/payment/woovi_webhooks|callback");

        echo "Response from webhook handler: \n";
        var_dump($response->getOutput());

        MockPhpStream::restore();
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