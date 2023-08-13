<?php

/**
 * This model relates OpenCart orders with charges on Woovi so we can
 * know which was the order for a specific charge, for example.
 *
 * @property ModelCheckoutOrder $model_checkout_order
 * @property DB $db
 * @property Loader $load
 *
 * @phpstan-import-type Charge from ControllerExtensionPaymentWoovi
 * @phpstan-type OpencartOrder array{order_id: string, order_status_id: string}
 * @phpstan-type WooviOrderData array{opencart_order_id: int, woovi_correlation_id: string, woovi_payment_link_url: string, woovi_qrcode_image_url: string, woovi_brcode: string, woovi_pixkey: string}
 */
class ModelExtensionPaymentWooviOrder extends Model
{
    /**
     * Relates a Woovi charge to an OpenCart order.
     *
     * @param Charge $charge Woovi charge data.
     */
    public function relateOrderWithCharge(string $opencartOrderId, string $wooviCorrelationID, array $charge): void
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "woovi_order` (
                `opencart_order_id`,
                `woovi_correlation_id`,
                `woovi_payment_link_url`,
                `woovi_qrcode_image_url`,
                `woovi_brcode`,
                `woovi_pixkey`
            ) VALUES ('" . $this->db->escape($opencartOrderId) . "',
                '" . $this->db->escape($wooviCorrelationID) . "',
                '" . $this->db->escape($charge["paymentLinkUrl"]) . "',
                '" . $this->db->escape($charge["qrCodeImage"]) . "',
                '" . $this->db->escape($charge["brCode"]) . "',
                '" . $this->db->escape($charge["pixKey"]) . "'
            );"
        );
    }

    /**
     * Return an Woovi order by Woovi correlationID or an empty array if not found
     *
     * @return WooviOrderData|array{}
     */
    public function getWooviOrderByCorrelationID(string $correlationID): array
    {
        /** @var object{row: WooviOrderData|array{}} $result */
        $result = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "woovi_order` WHERE `woovi_correlation_id` = '" . $this->db->escape($correlationID) . "'"
        );

        return $result->row;
    }

    /**
     * Return an Woovi order by OpenCart order ID or an empty array if not found.
     *
     * @return WooviOrderData|array{}
     */
    public function getWooviOrderByOpencartOrderId(string $opencartOrderId): array
    {
        /** @var object{row: WooviOrderData|array{}} $result */
        $result = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "woovi_order` WHERE `opencart_order_id` = '" . $this->db->escape($opencartOrderId) . "'"
        );

        return $result->row;
    }

    /**
     * Get OpenCart order by correlationID or returns `null` if not found.
     *
     * @return ?OpencartOrder
     */
    public function getOpencartOrderByCorrelationID(string $correlationID): ?array
    {
        $this->load->model("checkout/order");

        $wooviOrder = $this->getWooviOrderByCorrelationID($correlationID);

        if (empty($wooviOrder)) {
            return null;
        }

        $order = $this->model_checkout_order->getOrder(intval($wooviOrder["opencart_order_id"]));

        if (empty($order)) {
            return null;
        }

        /** @var OpencartOrder $order */
        return $order;
    }

    /**
     * Get order total value in cents.
     */
    public function getTotalValueInCents(string $opencartOrderId): int
    {
        /** @var object{row: array{total: int}} $result */
        $result = $this->db->query(
            "SELECT FLOOR(`total` * 100) AS `total` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $this->db->escape($opencartOrderId) . "'"
        );

        return $result->row["total"];
    }
}
