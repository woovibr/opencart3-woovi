<?php

namespace Opencart\Catalog\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * OpenCart event handlers.
 */
class WooviEvents extends Controller
{
    /**
     * Handle "catalog/view/common/success/before" event.
     * 
     * This adds the Woovi script along with an element in the HTML that will store a Woovi order on checkout success pages.
     * 
     * @see https://developers.woovi.com/docs/plugin#criando-o-plugin-de-order
     */
    public function handleCatalogViewCommonSuccessBeforeEvent(&$route, &$data, &$code)
    {
        // Ignore non-Pix payments.
        if (empty($this->session->data["woovi_correlation_id"])) return;

        $correlationID = $this->session->data["woovi_correlation_id"];

        // The `content_bottom` variable is used in the Twig template to display HTML code,
        // and it appears to be usually empty.
        // We put at the end of this variable an element with ID "woovi-order"
        // so that the Woovi plugin loaded later can insert the Woovi order.
        $data["content_bottom"] .= "<div id=\"woovi-order\"></div>";

        // We add our plugin's JavaScript code before the closing body tag.
        $data["footer"] = str_replace(
            "</body>",
            '<script src="'. $this->getPluginUrl($correlationID) .'"></script></body>',
            $data["footer"]
        );
    }

    /**
     * Handle "catalog/controller/checkout/success/before" event.
     * 
     * Ensures Woovi Qr Code is processed only on correct Pix orders.
     * 
     * We could have done this in the "catalog/view/common/success/before" event,
     * but OpenCart removes what the payment method and order id is from the session
     * before rendering the view.
     */
    public function handleCatalogControllerCheckoutSuccessBeforeEvent()
    {
        $lastOrderId = $this->session->data["order_id"] ?? $this->session->data["woovi_last_order_id"] ?? null;

        // Ignore non-Pix payments.
        if (! array_key_exists("woovi_correlation_id", $this->session->data)
            || ! $lastOrderId) return;

        // Ensures that the current correlationID matches the latest order in the session.
        $this->load->model("extension/woovi/payment/woovi_order");
        
        $wooviOrder = $this->model_extension_woovi_payment_woovi_order->getWooviOrderByCorrelationID($this->session->data["woovi_correlation_id"]);

        // Ignore if correlationID is not registered.
        if (! $wooviOrder) return;

        $wooviOpencartOrderId = (int) $wooviOrder["opencart_order_id"];

        // Prevents the plugin from rendering if the correlationID does not match
        // the lastest order.
        if ($lastOrderId != $wooviOpencartOrderId) {
            unset($this->session->data["woovi_correlation_id"]);
        }

        // Store latest order for future requests.
        // OpenCart removes order ID from the session on `checkout/success` controller.
        $this->session->data["woovi_last_order_id"] = $lastOrderId;
    }

    /**
     * Make url of Woovi plugin.
     */
    private function getPluginUrl(string $correlationID)
    {
        $appId = $this->config->get("payment_woovi_app_id");

        return "https://plugin.woovi.com/v1/woovi.js?appID=" . $appId . "&correlationID=" . $correlationID . "&node=woovi-order";
    }
}