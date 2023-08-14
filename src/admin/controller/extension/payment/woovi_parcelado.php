<?php

/**
 * Settings controller for Woovi Parcelado.
 */
class ControllerExtensionPaymentWooviParcelado extends Controller
{
    /**
     * Show or update the settings for the Parcelado payment method.
     */
    public function index(): void
    {
        $this->load->controller(
            "extension/woovi/base_config/index",
            [
                "key" => "woovi_parcelado",
            ]
        );
    }
}