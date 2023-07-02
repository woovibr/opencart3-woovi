<?php

namespace Opencart\Admin\Model\Extension\Woovi\Payment;

use Opencart\System\Engine\Model;

/**
 * Operations on the database layer.
 *
 * This adds the events and creates a table of relationships between orders and charges, for example.
 */
class Woovi extends Model
{
    /**
     * Installs the necessary structures for the extension to work, such as the table to track charges on orders.
     */
    public function install()
    {
        $this->createOrdersTable();
    }

    /**
     * Create orders table.
     *
     * The table is used to relate a OpenPix charge to an OpenCart order.
     */
    private function createOrdersTable()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `". DB_PREFIX ."woovi_orders` (
                `opencart_order_id` INT(11) NOT NULL,
                `woovi_correlation_id` VARCHAR(50) NOT NULL,
                UNIQUE INDEX `opencart_order_id` (`opencart_order_id`),
                UNIQUE INDEX `woovi_correlation_id` (`woovi_correlation_id`)
            )"
        );
    }
}
