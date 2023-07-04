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
            ) VALUES ('" . $opencartOrderId . "',
                '" . $wooviCorrelationID . "',
                '" . $charge["paymentLinkUrl"] . "',
                '" . $charge["qrCodeImage"] . "',
                '" . $charge["brCode"] . "',
                '" . $charge["pixKey"] . "'
            );"
        );
    }

    /**
     * Get an Woovi order by correlationID.
     */
    public function getWooviOrderByCorrelationID(string $correlationID)
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "woovi_order` WHERE `woovi_correlation_id` = '" . $correlationID . "'"
        )->row;
    }

    /**
     * Get order total value in cents.
     */
    public function getTotalValueInCents(int $opencartOrderId): int
    {
        return $this->db->query(
            "SELECT FLOOR(`total` * 100) AS `total` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int) $opencartOrderId . "'"
        )->row["total"];
    }
}