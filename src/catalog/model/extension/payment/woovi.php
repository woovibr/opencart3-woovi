<?php

/**
 * Add Pix payment method to OpenCart.
 * 
 * @property Loader $load
 * @property Language $language
 */
class ModelExtensionPaymentWoovi extends Model
{
    /**
     * Called when OpenCart show available payment methods to user.
     * 
     * @return array{title: string, code: string, sort_order: int}
     */
    public function getMethod(): array
    {
        $this->load->language("extension/payment/woovi");

        $paymentMethodTitle = $this->config->get("payment_woovi_pix_payment_method_title");

        if (empty($paymentMethodTitle)) {
            $paymentMethodTitle = $this->language->get("heading_title");
        }

        return [
            "title" => $paymentMethodTitle,
            "code" => "woovi",
            "sort_order" => 0,
        ];
    }
}
