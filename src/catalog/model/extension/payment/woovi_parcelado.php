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
    public const METHOD_CODE = "woovi_parcelado";

    /**
     * Prefix of config keys.
     */
    public const CONFIG_PREFIX = "payment_" . self::METHOD_CODE;

    /**
     * Called when OpenCart show available payment methods to user.
     *
     * @return array{title: mixed, code: string, sort_order: int}
     */
    public function getMethod(): array
    {
        $this->load->language("extension/payment/" . self::METHOD_CODE);

        $paymentMethodTitle = $this->config->get(self::CONFIG_PREFIX . "_payment_method_title");

        if (empty($paymentMethodTitle)) {
            $paymentMethodTitle = $this->language->get("heading_title");
        }

        return [
            "title" => $paymentMethodTitle,
            "code" => self::METHOD_CODE,
            "sort_order" => 0,
        ];
    }
}
