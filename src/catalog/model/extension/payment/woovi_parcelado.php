<?php

/**
 * Add Woovi Parcelado payment method to OpenCart.
 *
 * @property Loader $load
 * @property Language $language
 * @property Config $config
 */
class ModelExtensionPaymentWooviParcelado extends Model
{
    /**
     * The code of payment method.
     */
    public const METHOD_KEY = "woovi_parcelado";

    /**
     * Prefix of config keys.
     */
    public const CONFIG_PREFIX = "payment_" . self::METHOD_KEY;

    /**
     * Called when OpenCart show available payment methods to user.
     *
     * @return array{title: mixed, code: string, sort_order: int}
     */
    public function getMethod(): array
    {
        $this->load->language("extension/payment/" . self::METHOD_KEY);

        $paymentMethodTitle = $this->config->get(self::CONFIG_PREFIX . "_payment_method_title");

        if (empty($paymentMethodTitle)) {
            $paymentMethodTitle = $this->language->get("heading_title");
        }

        return [
            "title" => $paymentMethodTitle,
            "code" => self::METHOD_KEY,
            "sort_order" => 0,
        ];
    }
}
