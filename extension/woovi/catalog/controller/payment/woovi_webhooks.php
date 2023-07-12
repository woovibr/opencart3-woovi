<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * Invoked by the Woovi API.
 * 
 * @property \Opencart\System\Engine\Loader $load
 * @property \Opencart\System\Library\Request $request
 * @property \Opencart\System\Library\Response $response
 * @property \Opencart\System\Library\Language $language
 * @property \Opencart\System\Engine\Config $config
 * @property \Opencart\Catalog\Model\Checkout\Order $model_checkout_order
 * @property \Opencart\Catalog\Model\Extension\Woovi\Payment\WooviOrder $model_extension_woovi_payment_woovi_order
 * @property \OpenPix\PhpSdk\Client $woovi_api_client
 * @property \Woovi\Opencart\Logger $woovi_logger
 * 
 * @phpstan-type ValidatedWebhookPayload array{event: string, charge: array{correlationID: string}}
 */
class WooviWebhooks extends Controller
{
    /**
     * Executed when an event that we are monitoring in the API occurs,
     * such as a PIX transaction received by it.
     */
    public function callback(): void
    {
        $this->load->helper("extension/woovi/library"); // Setup API client.
        $this->load->language("extension/woovi/payment/woovi");
        $this->load->model("extension/woovi/payment/woovi_order");
        $this->load->model("checkout/order");

        $payload = $this->getValidatedWebhookPayload();
        if (is_null($payload)) return;

        $this->handleWebhookEvents($payload);
    }

    /**
     * Dispatch webhook to appropriate handler.
     * 
     * @param ValidatedWebhookPayload $payload
     */
    private function handleWebhookEvents(array $payload): void
    {
        $event = $payload["event"];
        
        if ($event === "teste_webhook") {
            $this->emitJson(["message" => "Success."]);
            $this->woovi_logger->debug("Test Webhook received.", "catalog/webhooks");
            return;
        }

        // @TODO: Implement this.
        if($event === "opencart-configure") {
            $this->emitJson(["message" => "Success."]);
            $this->woovi_logger->debug("Configure webhooks.", "catalog/webhooks");
            return;
        }

        if ($event === "OPENPIX:CHARGE_COMPLETED") {
            $this->handleChargeCompletedWebhook($payload);
            return;
        }
    }

    /**
     * Executed when an PIX transaction is received.
     * 
     * @param ValidatedWebhookPayload $payload
     */
    private function handleChargeCompletedWebhook(array $payload): void
    {
        $correlationID = $payload["charge"]["correlationID"];

        $order = $this->model_extension_woovi_payment_woovi_order->getOpencartOrderByCorrelationID($correlationID);

        if (empty($order)) {
            $this->woovi_logger->notice("Cound not find OpenCart order with correlation ID `" . $correlationID . "`", "catalog/webhooks");
            $this->emitJson(["error" => "Order not found."], 404);

            return;
        }

        $orderStatusId = $order["order_status_id"];

        /** @var string $newStatusId */
        $newStatusId = $this->config->get("payment_woovi_order_status_when_paid_id");

        // Ignore webhook event if order is already on paid status.
        if ($newStatusId == $orderStatusId) {
            $this->emitJson(["message" => "Success."]);
            $this->woovi_logger->debug("Order already paid: " . $order["order_id"], "catalog/webhooks");

            return;
        }
        
        $comment = $this->language->get("The payment was confirmed by Woovi.");
        $notifyCustomer = !! $this->config->get("payment_woovi_notify_customer");

        $this->model_checkout_order->addHistory(
            intval($order["order_id"]),
            intval($newStatusId),
            $comment,
            $notifyCustomer
        );

        $this->emitJson(["message" => "Success."]);
    }

    /**
     * Validates the webhook request and returns validated data or `null` if an error occurs.
     * 
     * @return ValidatedWebhookPayload
     */
    private function getValidatedWebhookPayload(): ?array
    {        
        $rawPayload = strval(file_get_contents("php://input"));
        
        /** @var array<array-key, mixed> $headers */
        $headers = getallheaders();

        $signature = $headers["x-webhook-signature"] ?? "";
        $signature = is_string($signature) ? $signature : "";
       
        if (! $this->isValidSignature($rawPayload, $signature)) {
            $serializedRequest = json_encode($this->request, JSON_PRETTY_PRINT);

            $this->woovi_logger->warning(
                "Invalid webhook signature from request " . $serializedRequest,
                "catalog/webhooks"
            );

            $this->emitJson(["error" => "Invalid webhook signature."], 401);
            return null;
        }

        $payload = json_decode($rawPayload, true);


        $isJsonInvalid = json_last_error() !== JSON_ERROR_NONE;

        if ($isJsonInvalid
            || ! is_array($payload)
            || ! $this->isValidWebhookPayload($payload)) {
            $serializedRequest = json_encode($this->request, JSON_PRETTY_PRINT);

            $this->woovi_logger->warning(
                "Invalid webhook payload from request " . $serializedRequest,
                "catalog/webhooks"
            );

            $this->emitJson(["error" => "Invalid webhook payload."], 400);
            return null;
        }

        if ($this->isPixDetachedPayload($payload)) {
            $this->emitJson(["error" => "Pix Detached."]);
            return null;
        }

        /** @var ValidatedWebhookPayload $payload */
        return $payload;
    }

    /**
     * Check if webhook signature is valid.
     */
    private function isValidSignature(string $rawPayload, string $signature): bool
    {
        if (empty($signature) || ! $this->woovi_api_client->webhooks()->isWebhookValid($rawPayload, $signature)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if webhook payload is valid.
     * 
     * @param array<array-key, mixed> $payload The payload data to be validated.
     * 
     * @return bool Returns `true` if the data contains any of the required keys, otherwise returns `false`.
     */
    private function isValidWebhookPayload(array $payload): bool
    {
        return ! empty($payload["event"]);
    }

    /**
     * Checks if the payload is of an detached pix.
     * 
     * @param array<array-key, mixed> $payload
     */
    private function isPixDetachedPayload(array $payload): bool
    {
        if (empty($payload["pix"])) {
            return false;
        }

        if (! empty($payload["charge"]) && ! empty($payload["charge"]["correlationID"])) {
            return false;
        }

        return true;
    }

    /**
     * Encode given data as JSON and emit it with correct `Content-type`.
     *
     * @param mixed $data
     */
    private function emitJson($data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(strval(json_encode($data)));
    }
}
