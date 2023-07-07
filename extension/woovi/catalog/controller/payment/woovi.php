<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Payment;

use Exception;
use Opencart\System\Engine\Controller;
use OpenPix\PhpSdk\ApiErrorException;
use Psr\Http\Client\ClientExceptionInterface;

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

        if (! $this->isConfirmationRequestValid()) return;

        $this->addOrderConfirmation();

        $correlationID = $this->generateCorrelationID();
        $orderValueInCents = $this->getOrderValueInCents();
        $customerData = $this->getCustomerData();

        $createChargeResult = $this->createWooviCharge($correlationID, $orderValueInCents, $customerData);

        if (empty($createChargeResult["correlationID"]) || empty($createChargeResult["charge"])) {
            $this->emitError($this->language->get("woovi_api_error_message"));
            return;
        }

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
     * Validate the user's order confirmation request.
     */
    private function isConfirmationRequestValid(): bool
    {
        if (! isset($this->session->data["order_id"])) {
            $this->emitError($this->language->get("woovi_error_order"));
            return false;
        }

        if (! isset($this->session->data["payment_method"])
            || $this->session->data["payment_method"] != "woovi") {
            $this->emitError($this->language->get("woovi_error_payment_method"));
            return false;
        }

        $taxID = $this->request->post["tax_id"] ?? "";

        // Shows an error if it is invalid in both cases: CPF and CNPJ.
        if (! ($this->isCPFValid($taxID) ^ $this->isCNPJValid($taxID))) {
            $this->emitError([
                "tax_id" => $this->language->get("woovi_error_tax_id"),
            ]);
            return false;
        }

        return true;
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
    private function createWooviCharge(string $correlationID, int $orderValueInCents, ?array $customerData): array
    {
        $this->load->controller("extension/woovi/startup/api"); // Setup API client.

        /** @var \OpenPix\PhpSdk\Client $client */
        $client = $this->registry->get("woovi_php_sdk");

        $chargeData = [
            "correlationID" => $correlationID,
            "value" => $orderValueInCents,
            "customer" => $this->getCustomerData(),
        ];

        try {
            return $client->charges()->create($chargeData);
        } catch (Exception $e) {
            $this->handleException($e);
        }
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
     * Gets the consumer data or returns null if not possible.
     */
    private function getCustomerData(): ?array
    {
        $phone = $this->normalizePhone($this->customer->getTelephone());
        $taxID = $this->request->post["tax_id"] ?? null;

        $customer = $this->session->data["customer"];

        $customerData = [
            "name" => $customer["firstname"] . " " . $customer["lastname"],
            "email" => $customer["email"],
        ];

        if (! empty($phone)) $customerData["phone"] = $phone;
        if (! empty($taxID)) $customerData["taxID"] = $taxID;

        return $customerData;
    }

    /**
     * Handle exceptions catched by extension.
     */
    private function handleException(Exception $e): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        if ($e instanceof ApiErrorException
            || $e instanceof ClientExceptionInterface) {
            $this->emitError($this->language->get("woovi_api_error_message"));
            return;
        }

        throw $e;
    }

    /**
     * Normalize customer telephone.
     */
    private function normalizePhone(string $phone): ?string
    {
        if (empty($phone)) return null;

        if (strlen($phone) > 11) {
            return preg_replace("/^0|\D+/", "", $phone);
        }

        return "55" . preg_replace("/^0|\D+/", "", $phone);
    }

    /**
     * Emit an error response.
     *
     * @param string|array|mixed $error If it is an array, in the format:
     *      ```php
     *      ["field_name" => "field_error_message"]
     *      ```
     *      It can be a string, where only an danger alert will appear at checkout.
     */
    private function emitError($error)
    {
        if (is_string($error)) $error = ["warning" => $error];

        $this->emitJson(["error" => $error]);
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
     * Check if CPF is valid.
     */
    private function isCPFValid(string $cpf): bool
    {
        // CPF must be not empty.
        if (empty($cpf)) {
            return false;
        }

        // Remove non-numeric characters from the CPF.
        $cpf = preg_replace("/[^0-9]/", "", $cpf);

        // CPF length must be 11.
        if (strlen($cpf) != 11) {
            return false;
        }

        // Check if the CPF has 11 repeated digits.
        if (preg_match("/(\d)\1{10}/", $cpf)) {
            return false;
        }

        // Calculates the check digits to verify that the CPF is valid.
        for ($i = 9; $i < 11; $i++) {
            for ($sum = 0, $j = 0; $j < $i; $j++) {
                $sum += $cpf[$j] * ($i + 1 - $j);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ($cpf[$j] != $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if CNPJ is valid.
     */
    private function isCNPJValid(string $cnpj): bool
    {
        // CNPJ must be not empty.
        if (empty($cnpj)) {
            return false;
        }

        // Remove non-numeric characters.
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // CNPJ length must be 14.
        if (strlen($cnpj) != 14) {
            return false;
        }

        // Check if the CNPJ has repeating digits.
        if (preg_match("/(\d)\1{13}/", $cnpj)) {
            return false;
        }

        $multipliers = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        // Calculate the first verification digit.
        for ($i = 0, $sum = 0; $i < 12; $sum += $cnpj[$i] * $multipliers[++$i]);

        // Check the first verification digit.
        if ($cnpj[12] != (($sum %= 11) < 2 ? 0 : 11 - $sum)) {
            return false;
        }

        // Calculate the second verification digit.
        for ($i = 0, $sum = 0; $i <= 12; $sum += $cnpj[$i] * $multipliers[$i++]);

        if ($cnpj[13] != (($sum %= 11) < 2 ? 0 : 11 - $sum)) {
            return false;
        }

        return true;
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
