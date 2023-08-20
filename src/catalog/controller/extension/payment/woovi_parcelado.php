<?php

/**
 * Add endpoints for Woovi Parcelado payment method.
 */
class ControllerExtensionPaymentWooviParcelado extends Controller
{
    /**
     * Just an alias to Woovi Pix controller action.
     */
    public function index(): string
    {
        return $this->load->controller("extension/payment/woovi");
    }
}