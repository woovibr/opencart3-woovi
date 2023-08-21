<?php

/**
 * Add endpoints for Woovi Parcelado payment method.
 *
 * @property Loader $load
 */
class ControllerExtensionPaymentWooviParcelado extends Controller
{
    /**
     * Just an alias to Woovi Pix controller action.
     *
     * @return mixed
     */
    public function index()
    {
        return $this->load->controller("extension/payment/woovi");
    }
}
