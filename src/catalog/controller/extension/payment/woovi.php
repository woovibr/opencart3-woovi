<?php

use OpenPix\PhpSdk\ApiErrorException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Add endpoints for pix payment method.
 *
 * @property \Opencart\System\Engine\Loader $load
 * @property \Opencart\System\Library\Session $session
 * @property \Opencart\System\Library\Url $url
 * @property \Opencart\System\Library\Request $request
 * @property \Opencart\System\Library\Response $response
 * @property \Opencart\System\Library\Language $language
 * @property \Opencart\System\Engine\Config $config
 * @property \Opencart\System\Library\Cart\Customer $customer
 * @property \Opencart\Catalog\Model\Checkout\Order $model_checkout_order
 * @property \Opencart\Catalog\Model\Extension\Woovi\Payment\WooviOrder $model_extension_woovi_payment_woovi_order
 * @property \OpenPix\PhpSdk\Client $woovi_api_client
 * @property \Woovi\Opencart\Logger $woovi_logger
 * 
 * @phpstan-type CreateChargeResult array{correlationID: string, charge: Charge}
 * @phpstan-type Charge array{paymentLinkUrl: string, qrCodeImage: string, brCode: string, pixKey: string, correlationID: string}
 * @phpstan-type CustomerData array{name: string, email: string, taxID?: string, phone?: string}
 */
class ControllerExtensionPaymentWoovi extends Controller
{
    /**
     * Scope of log messages.
     */
    private const LOG_SCOPE = "catalog/checkout";

    /**
     * Called when the user selects the "Pix" payment method.
     *
     * This method will return an order confirmation button.
     */
    public function index(): string
    {
        $this->load->language("extension/woovi/payment/woovi");

        $showTaxIdInput = empty($this->getTaxIDFromSession());

        return $this->load->view("extension/woovi/payment/woovi", [
            "language" => $this->config->get("config_language"),
            "lang" => $this->language->all(),
            "show_tax_id_input" => $showTaxIdInput,
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
        $this->load->model("extension/woovi/payment/woovi_order");
        $this->load->language("extension/woovi/payment/woovi");

        if (! $this->isConfirmationRequestValid()) return;

        $orderId = $this->session->data["order_id"];

        $this->addOrderConfirmation();

        $correlationID = $this->generateCorrelationID();
        $orderValueInCents = $this->getOrderValueInCents();
        $customerData = $this->getCustomerData();

        $createChargeResult = $this->createWooviCharge($correlationID, $orderValueInCents, $customerData);

        // An error ocurred and it is logged.
        if (empty($createChargeResult)) return;

        if (! empty($orderId)) {
            $this->relateOrderWithWooviCharge($orderId, $createChargeResult);
        }

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
        if (empty($this->session->data["order_id"])) {
            $this->emitError($this->language->get("No order ID in the session!"));

            return false;
        }

        if (empty($this->session->data["payment_method"])
            || $this->session->data["payment_method"] != "woovi") {
            $this->emitError($this->language->get("Payment method is incorrect!"));

            return false;
        }

        $taxID = $this->getTaxID();

        // Shows an error if it is invalid in both cases: CPF and CNPJ.
        if (! ($this->isCPFValid($taxID) ^ $this->isCNPJValid($taxID))) {
            $error = ! empty($this->getTaxIDFromSession())
                ? ["warning" => $this->language->get("CPF/CNPJ invalid! Change the CPF/CNPJ field in your account settings page or on this page if you see the field.")]
                : ["tax_id" => $this->language->get("CPF/CNPJ invalid!")];
            $this->emitError($error);

            return false;
        }

        // Check if an order has already been registered with a pix charge.
        $orderId = intval($this->session->data["order_id"]);

        $wooviOrder = $this->model_extension_woovi_payment_woovi_order->getWooviOrderByOpencartOrderId($orderId);

        if (! empty($wooviOrder)) {
            $this->emitError(["warning" => $this->language->get("There is already a charge for this order!")]);

            return false;
        }

        return true;
    }

    /**
     * Get CPF/CNPJ from session.
     */
    private function getTaxIDFromSession(): string
    {
        $taxIdCustomFieldId = $this->config->get("payment_woovi_tax_id_custom_field_id");
        $customFields = $this->session->data["customer"]["custom_field"];

        if (! empty($customFields["account"][$taxIdCustomFieldId])) {
            return $customFields["account"][$taxIdCustomFieldId];
        }

        if (! empty($customFields[$taxIdCustomFieldId])) {
            return $customFields[$taxIdCustomFieldId];
        }

        return "";
    }

    /**
     * Get CPF/CNPJ from session or request POSTed data.
     */
    private function getTaxID(): string
    {
        $taxID = $this->getTaxIDFromSession();

        if (! empty($taxID)) return $taxID;

        return $this->request->post["tax_id"] ?? "";
    }

    /**
     * Adds the user's order confirmation to the database.
     *
     * This will subtract stock and change order status, for example.
     */
    private function addOrderConfirmation(): void
    {
        /** @var string $orderStatusId  */
        $orderStatusId = $this->config->get("payment_woovi_order_status_when_waiting_id");

        $this->load->model("checkout/order");
        $this->model_checkout_order->addHistory(
            $this->session->data["order_id"],
            intval($orderStatusId)
        );
    }

    /**
     * Get current order value in cents.
     */
    private function getOrderValueInCents(): int
    {
        return $this->model_extension_woovi_payment_woovi_order->getTotalValueInCents($this->session->data["order_id"]);
    }

    /**
     * Create an charge and send to Woovi API.
     * 
     * @param CustomerData $customerData
     * @return CreateChargeResult
     */
    private function createWooviCharge(string $correlationID, int $orderValueInCents, array $customerData): ?array
    {
        $this->load->helper("extension/woovi/library"); // Setup API client & logger.

        $chargeData = [
            "correlationID" => $correlationID,
            "value" => $orderValueInCents,
            "customer" => $customerData,
        ];

        try {
            $createChargeResult = $this->woovi_api_client->charges()->create($chargeData);
        } catch (ApiErrorException|ClientExceptionInterface $e) {
            $this->load->language("extension/woovi/payment/woovi");
            $this->woovi_logger->error($e, self::LOG_SCOPE);
        }

        // It could be an error in the App ID.
        /** @var list<array{message: string}> $errors */
        $errors = $createChargeResult["errors"] ?? [];

        if (! empty($errors[0]["message"])) {
            $this->woovi_logger->error(
                $errors[0]["message"],
                self::LOG_SCOPE
            );
        }

        // We notify the user when there is any type of error.
        if (empty($createChargeResult["correlationID"]) || empty($createChargeResult["charge"])) {
            $this->emitError($this->language->get("An error occurred while creating the Pix charge."));
            return null;
        }

        /** @var CreateChargeResult $createChargeResult */
        return $createChargeResult;
    }

    /**
     * Relates the OpenCart order to the charge on Woovi.
     * 
     * @param CreateChargeResult $createChargeResult
     */
    private function relateOrderWithWooviCharge(int $opencartOrderId, array $createChargeResult): void
    {
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
     * 
     * @return CustomerData
     */
    private function getCustomerData(): array
    {
        $phone = $this->normalizePhone($this->customer->getTelephone());
        $taxID = $this->getTaxID();

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
    private function emitError($error): void
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
        $this->response->setOutput((string) json_encode($data));
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
        /** @var string $cpf */
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // CPF length must be 11.
        if (strlen($cpf) != 11) {
            return false;
        }

        // Check if the CPF has 11 repeated digits.
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calculates the check digits to verify that the CPF is valid.
        for ($i = 9; $i < 11; $i++) {
            for ($sum = 0, $j = 0; $j < $i; $j++) {
                $sum += intval($cpf[$j]) * ($i + 1 - $j);
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
        /** @var string $cnpj */
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // CNPJ length must be 14.
        if (strlen($cnpj) != 14) {
            return false;
        }

        // Check if the CNPJ has repeating digits.
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        /** @var array<int> $digits */
        $digits = array_map('intval', str_split($cnpj));

        $multipliers = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        // Calculate the first verification digit.
        for ($i = 0, $sum = 0; $i < 12; $sum += $digits[$i] * $multipliers[++$i]);

        // Check the first verification digit.
        $firstVerificationDigit = $digits[12];
        if ($firstVerificationDigit != (($sum %= 11) < 2 ? 0 : 11 - $sum)) {
            return false;
        }

        // Calculate the second verification digit.
        for ($i = 0, $sum = 0; $i <= 12; $sum += $digits[$i] * $multipliers[$i++]);

        $secondVerificationDigit = $digits[13];
        if ($secondVerificationDigit != (($sum %= 11) < 2 ? 0 : 11 - $sum)) {
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
