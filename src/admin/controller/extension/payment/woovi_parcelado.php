<?php

/**
 * Settings controller for Woovi Parcelado.
 * 
 * @property Loader $load
 */
class ControllerExtensionPaymentWooviParcelado extends Controller
{
    /**
     * Show or update the settings for the Parcelado payment method.
     */
    public function index(): void
    {
        $this->load->controller("extension/payment/woovi/index");
    }
}
