<?php

namespace Opencart\Admin\Model\Extension\Woovi\Payment;

use Opencart\System\Engine\Model;

/**
 * This model integrates the extension with the OpenCart database.
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
        $this->installEvents();
        $this->createWooviOrderTable();
    }

    /**
     * Executed when the extension is uninstalled.
     */
    public function uninstall()
    {
        $this->uninstallEvents();
    }

    /**
     * Create woovi orders table.
     *
     * The table is used to relate a OpenPix charge to an OpenCart order.
     */
    private function createWooviOrderTable()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `". DB_PREFIX ."woovi_order` (
                `opencart_order_id` INT(11) NOT NULL,
                `woovi_correlation_id` VARCHAR(50) NOT NULL,
                `woovi_payment_link_url` VARCHAR(255) NOT NULL,
                `woovi_qrcode_image_url` VARCHAR(255) NOT NULL,
                `woovi_brcode` VARCHAR(500) NOT NULL,
                `woovi_pixkey` VARCHAR(200) NOT NULL,
                UNIQUE INDEX `opencart_order_id` (`opencart_order_id`),
                UNIQUE INDEX `woovi_correlation_id` (`woovi_correlation_id`)
            )"
        );
    }

    /**
     * Install the extension's event listeners in the OpenCart database.
     */
    private function installEvents()
    {
        $this->load->model("setting/event");

        $this->model_setting_event->deleteEventByCode("woovi_catalog_view_common_success_before");
        $this->model_setting_event->deleteEventByCode("woovi_catalog_controller_checkout_success_before");

        $this->model_setting_event->addEvent([
            "code" => "woovi_catalog_controller_checkout_success_before",
            "description" => "Ensures Woovi Qr Code is processed only on correct Pix orders.",
            "trigger" => "catalog/controller/checkout/success/before",
            "action" => "extension/woovi/payment/woovi_events|handleCatalogControllerCheckoutSuccessBeforeEvent",
            "status" => true,
            "sort_order" => 0,
        ]);

        $this->model_setting_event->addEvent([
            "code" => "woovi_catalog_view_common_success_before",
            "description" => "Add a QR Code on checkout success pages.",
            "trigger" => "catalog/view/common/success/before",
            "action" => "extension/woovi/payment/woovi_events|handleCatalogViewCommonSuccessBeforeEvent",
            "status" => true,
            "sort_order" => 0,
        ]);
    }

    /**
     * Uninstall the extension's event listeners in the OpenCart database.
     */
    private function uninstallEvents()
    {
        $this->load->model("setting/event");

        $this->model_setting_event->deleteEventByCode("woovi_catalog_view_common_success_before");
        $this->model_setting_event->deleteEventByCode("woovi_catalog_controller_checkout_success_before");
    }
}
