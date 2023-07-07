<?php

namespace Opencart\Catalog\Model\Extension\Woovi\Payment;

use Opencart\System\Engine\Model;

/**
 * This model relates OpenCart orders with charges on Woovi so we can
 * know which was the order for a specific charge, for example.
 */
class WooviOrder extends Model
{
    /**
     * Relates a Woovi charge to an OpenCart order.
     */
    public function relateOrderWithCharge(int $opencartOrderId, string $wooviCorrelationID, array $charge): void
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
     * Get an Woovi order by correlationID.
     */
    public function getWooviOrderByCorrelationID(string $correlationID)
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "woovi_order` WHERE `woovi_correlation_id` = '" . $this->db->escape($correlationID) . "'"
        )->row;
    }

    /**
     * Get an Woovi order by OpenCart order ID.
     */
    public function getWooviOrderByOpencartOrderId(string $opencartOrderId)
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "woovi_order` WHERE `opencart_order_id` = '" . $this->db->escape($opencartOrderId) . "'"
        )->row;
    }


    /**
     * Get order total value in cents.
     */
    public function getTotalValueInCents(int $opencartOrderId): int
    {
        return $this->db->query(
            "SELECT FLOOR(`total` * 100) AS `total` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int) $this->db->escape($opencartOrderId) . "'"
        )->row["total"];
    }
}
