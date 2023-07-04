<?php

namespace Opencart\Catalog\Model\Extension\Woovi\Payment;

use Opencart\System\Engine\Model;

/**
 * Add Pix payment method to OpenCart.
 */
class Woovi extends Model
{
    /**
     * Called when OpenCart show available payment methods to user.
     */
    public function getMethod(): array
    {
        $this->load->language("extension/woovi/payment/woovi");

        return [
            "title" => $this->language->get("heading_title"),
            "code" => "woovi",
            "sort_order" => $this->config->get("payment_woovi_sort_order"),
        ];
    }
}
