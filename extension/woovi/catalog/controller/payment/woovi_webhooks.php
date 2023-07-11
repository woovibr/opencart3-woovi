<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * Invoked by the Woovi API.
 *
 * @property \OpenPix\PhpSdk\Client $woovi_api_client
 * @property \Woovi\Opencart\Logger $woovi_logger
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

        $payload = $this->getValidatedPayload();
        if (is_null($payload)) return;

        $this->handleWebhookEvents($payload);
    }

    public function isTestPayload($payload) : bool {
        if(isset($payload['evento']) && $payload['evento'] === 'teste_webhook') {
            return true;
        }

        if(isset($payload['event']) && $payload['event'] === 'teste_webhook') {
            return true;
        }

        return false;
    }

    public function handleWebhookEvents($payload): void {

        if($this->isTestPayload($payload)) {
            $this->emitJson(["message" => "Success."]);
            $this->woovi_logger->debug("Test Webhook received.", "catalog/webhooks");
            return;
        }


        if(!isset($payload['event']) || empty($payload['event'])) {
            $this->emitJson(["error" => "Invalid webhook payload."], 400);
            return;
        }

        $event = $payload['event'];

        // @todo: implements this
        if($event === 'opencart-configure') {
            $this->emitJson(["message" => "Success."]);
            $this->woovi_logger->debug("Configure Webhook configure.", "catalog/webhooks");
            return;
        }

        if (
            $event === 'OPENPIX:TRANSACTION_RECEIVED' ||
            $event === 'OPENPIX:CHARGE_COMPLETED'
        ) {
            $this->handleTransactionReceivedWebhook($payload);
            return;
        }


    }
    /**
     * Executed when an PIX transaction is received.
     */
    private function handleTransactionReceivedWebhook(array $payload): void
    {
        $order = $this->model_extension_woovi_payment_woovi_order->getOpencartOrderByCorrelationID($payload["charge"]["correlationID"]);

        if (empty($order)) {
            $this->woovi_logger->notice("Cound not find OpenCart order with correlation ID `" . $payload["charge"]["correlationID"] . "`", "catalog/webhooks");
            $this->emitJson(["error" => "Order not found."], 400);
            return;
        }

        $newStatusId = (int) $this->config->get("payment_woovi_order_status_when_paid_id");
        $comment = $this->language->get("The payment was confirmed by Woovi.");
        $notifyCustomer = !! $this->config->get("payment_woovi_notify_customer");

        $this->model_checkout_order->addHistory(
            $order["order_id"],
            $newStatusId,
            $comment,
            $notifyCustomer
        );

        $this->emitJson(["message" => "Success."]);
    }

    private function validSignature($payload, $signature): bool
    {
        if (empty($signature) || ! $this->woovi_api_client->webhooks()->isWebhookValid($payload, $signature)) {
            return false;
        }

        return true;
    }

    public function isValidWebhookPayload($data)
    {
        if (!isset($data['event']) || empty($data['event'])) {
            if (!isset($data['evento']) || empty($data['evento'])) {
                return false;
            }
        }

        // @todo remove it and update evento to event

        return true;
    }

    /**
     * Validates the webhook request and returns validated data or `null` if an error occurs.
     */
    private function getValidatedPayload(): ?array
    {
        $rawPayload = file_get_contents("php://input");
        $headers = getallheaders();

        $signature = $headers["X-Webhook-Signature"];

        if(! $this->validSignature($rawPayload, $signature)) {
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

        if (
            $isJsonInvalid ||
            !is_array($payload) ||
            !$this->isWebhookPayloadValid($payload)) {
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

        return $payload;
    }

    /**
     * Check if the provided data is a valid webhook payload.
     *
     * @param array $data The data to be validated.
     *
     * @return bool Returns true if the data contains any of the required keys (charge, pix, or event), otherwise returns false.
     */
    private function isWebhookPayloadValid(array $payload): bool
    {
        if (!isset($payload['event']) || empty($payload['event'])) {
            if (!isset($payload['evento']) || empty($payload['evento'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the payload is of an detached pix.
     */
    private function isPixDetachedPayload(array $payload): bool
    {
        if (! isset($payload["pix"])) {
            return false;
        }

        if (isset($payload["charge"])
            && isset($payload["charge"]["correlationID"])
        ) {
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
        $this->response->setOutput(json_encode($data));
    }
}
