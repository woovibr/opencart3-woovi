<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * Add endpoints for pix payment method.
 */
class Woovi extends Controller
{
    /**
     * Called when the user selects the "Pix" payment method.
     *
     * This method will return an order confirmation button.
     */
	public function index(): string
    {
		$this->load->language("extension/woovi/payment/woovi");

		return $this->load->view("extension/woovi/payment/woovi", [
            "language" => $this->config->get("config_language"),
            "lang" => $this->language->all(),
        ]);
	}

    /**
     * Confirms the user's order.
     *
     * Create correlationID, send an charge to OpenPix and
     * redirect user to checkout success page.
     */
    public function confirm(): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        $this->validateConfirmationRequest();
        $this->addOrderConfirmation();

        $correlationID = $this->generateCorrelationID();
        $orderValueInCents = $this->getOrderValueInCents();

        $createChargeResult = $this->charge($correlationID, $orderValueInCents);

        $this->relateOrderWithWooviCharge($this->session->data["order_id"], $createChargeResult);
        $this->persistCorrelationIDToCheckoutSuccess($correlationID);

        $this->emitJson([
            "redirect" => $this->url->link(
                "checkout/success",
                "language=" . $this->config->get("config_language"),
                true
            ),
        ]);
    }

    /**
     * Encode given data as JSON and emit it with correct `Content-type`.
     *
     * @param mixed $data
     */
    private function emitJson($data): void
    {
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Validate the user's order confirmation request.
     */
    private function validateConfirmationRequest(): void
    {
        if (! isset($this->session->data["order_id"])) {
            $this->emitJson([
                "error" => $this->language->get("woovi_error_order")
            ]);
            return;
        }

        if (! isset($this->session->data["payment_method"])
            || $this->session->data["payment_method"] != "woovi") {
            $this->emitJson([
                "error" => $this->language->get("woovi_error_payment_method"),
            ]);
            return;
        }
    }

    /**
     * Adds the user's order confirmation to the database.
     *
     * This will subtract stock and change order status, for example.
     */
    private function addOrderConfirmation()
    {
        $this->load->model("checkout/order");
        $this->model_checkout_order->addHistory(
            $this->session->data["order_id"],
            (int) $this->config->get("payment_woovi_order_status_id")
        );
    }

    /**
     * Get current order value in cents.
     */
    private function getOrderValueInCents(): int
    {
        $this->load->model("extension/woovi/payment/woovi_order");
        
        return $this->model_extension_woovi_payment_woovi_order->getTotalValueInCents($this->session->data["order_id"]);
    }

    /**
     * Create an charge and send to Woovi API.
     */
    private function charge(string $correlationID, int $orderValueInCents): array
    {
        $this->load->controller("extension/woovi/startup/api"); // Setup API client.

        /** @var \OpenPix\PhpSdk\Client $client */
        $client = $this->registry->get("woovi_php_sdk");

        // TODO: Handle exceptions.
        return $client->charges()->create([
            "correlationID" => $correlationID,
            "value" => $orderValueInCents,
        ]);
    }

    /**
     * Relates the OpenCart order to the charge on Woovi.
     */
    private function relateOrderWithWooviCharge(int $opencartOrderId, array $createChargeResult)
    {
        $this->load->model("extension/woovi/payment/woovi_order");
        $this->model_extension_woovi_payment_woovi_order->relateOrderWithCharge(
            $opencartOrderId,
            $createChargeResult["correlationID"],
            $createChargeResult["charge"]
        );
    }

    /**
     * Store the correlationID to be used in the next step:
     * display our JS plugin that shows a Qr Code to the user.
     */
    private function persistCorrelationIDToCheckoutSuccess(string $correlationID): void
    {
        $this->session->data["woovi_correlation_id"] = $correlationID;
    }

    /**
     * Generate a correlation ID for a Woovi charge.
     */
    private function generateCorrelationID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
