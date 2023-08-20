<?php

/**
 * This model integrates the extension with the OpenCart database.
 *
 * This adds the events and creates a table of relationships between orders and charges, for example.
 *
 * @property DB $db
 * @property Loader $load
 * @property ModelCustomerCustomField $model_customer_custom_field
 * @property ModelSettingSetting $model_setting_setting
 * @property ModelSettingEvent $model_setting_event
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelSettingExtension $model_setting_extension
 * @property Config $config
 */
class ModelExtensionPaymentWoovi extends Model
{
    /**
     * Current extension version.
     */
    public const CURRENT_VERSION = "1.0.0";

    /**
     * Regex for validating CPF/CNPJ field format.
     */
    private const TAX_ID_CUSTOM_FIELD_VALIDATION_REGEX = "/(^\s*\d{3}\.\d{3}\.\d{3}\-\d{2}\s*$)|(^\s*\d{11}\s*$)|(^\s*\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}\s*$)/";

    /**
     * Installs the necessary structures for the extension to work, such as the table to track charges on orders.
     */
    public function install(): void
    {
        $this->installEvents();
        $this->createWooviOrderTable();
        $this->installSettings();
        $this->installCustomFields();
        $this->upgrade();
    }

    /**
     * Executed when the extension is uninstalled.
     */
    public function uninstall(): void
    {
        $this->uninstallEvents();
    }

    /**
     * Install custom fields like CPF/CNPJ.
     */
    private function installCustomFields(): void
    {
        $fingerprintSettings = $this->model_setting_setting->getSetting("thirdparty_payment_woovi");
        $settings = $this->model_setting_setting->getSetting("payment_woovi");

        // Does not create if the extension has already been installed.
        if (! empty($fingerprintSettings)) {
            return;
        }

        $this->load->model("customer/custom_field");
        $this->load->model("localisation/language");

        // Add translated custom field descriptions.
        $languages = $this->model_localisation_language->getLanguages();

        $descriptions = [
            "tax_id" => "CPF/CNPJ",
            "address_number" => "Address number",
            "address_complement" => "Address complement",
        ];

        $descriptionTranslations = [];

        foreach ($languages as $language) {
            $languageCode = $language["code"];

            $this->language->load("extension/payment/woovi", $languageCode);
            $extensionTranslations = $this->language->get($languageCode);

            if (! ($extensionTranslations instanceof Language)) {
                $extensionTranslations = $this->language;
            }

            $extensionTranslations = $extensionTranslations->all();

            foreach ($descriptions as $customField => $description) {
                $languageId = $language["language_id"];
                $translation = $extensionTranslations[$description] ?? $description;

                $descriptionTranslations[$customField][$languageId] = [
                    "name" => $translation
                ];
            }
        }

        // Use default customer group ID.
        $customerGroupId = $this->config->get("config_customer_group_id");

        $taxIdCustomFieldId = $this->model_customer_custom_field->addCustomField([
            "custom_field_description" => $descriptionTranslations["tax_id"],
            "type" => "text",
            "validation" => self::TAX_ID_CUSTOM_FIELD_VALIDATION_REGEX,
            "location" => "account",
            "status" => 1,
            "value" => "",
            "sort_order" => 3,
            "custom_field_customer_group" => [
                [
                    "customer_group_id" => $customerGroupId,
                    "required" => 1,
                ],
            ],
        ]);

        $addressNumberCustomFieldId = $this->model_customer_custom_field->addCustomField([
            "custom_field_description" => $descriptionTranslations["address_number"],
            "type" => "text",
            "validation" => "",
            "location" => "address",
            "status" => 1,
            "value" => "",
            "sort_order" => 2,
            "custom_field_customer_group" => [
                [
                    "customer_group_id" => $customerGroupId,
                    "required" => 1,
                ],
            ],
        ]);

        $addressComplementCustomFieldId = $this->model_customer_custom_field->addCustomField([
            "custom_field_description" => $descriptionTranslations["address_complement"],
            "type" => "text",
            "validation" => "",
            "location" => "address",
            "status" => 1,
            "value" => "",
            "sort_order" => 3,
            "custom_field_customer_group" => [
                [
                    "customer_group_id" => $customerGroupId,
                ],
            ],
        ]);

        // Store settings.
        $settings["payment_woovi_tax_id_custom_field_id"] = $taxIdCustomFieldId;
        $settings["payment_woovi_address_number_custom_field_id"] = $addressNumberCustomFieldId;
        $settings["payment_woovi_address_complement_custom_field_id"] = $addressComplementCustomFieldId;

        $this->model_setting_setting->editSetting("payment_woovi", $settings);
    }

    /**
     * Install default settings like order statuses ID's.
     */
    private function installSettings(): void
    {
        $this->load->model("setting/setting");

        $this->installPaymentMethodSettings(
            "woovi",
            $this->language->get("Pay with Pix")
        );
        $this->installPaymentMethodSettings(
            "woovi_parcelado",
            $this->language->get("Pay with Woovi Parcelado")
        );
        $this->installGeneralSettings();
    }

    /**
     * Install General settings.
     */
    private function installGeneralSettings(): void
    {
        $settings = $this->model_setting_setting->getSetting("payment_woovi");

        if (! isset($settings["payment_woovi_app_id"])) {
            $settings["payment_woovi_app_id"] = "";
        }

        $this->model_setting_setting->editSetting("payment_woovi", $settings);
    }

    /**
     * Install default settings for a payment method.
     */
    private function installPaymentMethodSettings(string $group, string $methodTitle): void
    {
        $settings = $this->model_setting_setting->getSetting("payment_{$group}");

        // Use OpenCart configured order status.
        // Otherwise, use "pending" as default waiting status.
        // On OpenCart installer, the pending status ID is 1:
        // https://github.com/opencart/opencart/blob/e3ae482e66671167b44f86e798f07f8084561117/upload/install/opencart.sql#L1578
        $orderStatusWhenWaitingId = $settings["payment_{$group}_order_status_when_waiting_id"] ?? "";

        if (empty($orderStatusWhenWaitingId)) {
            $orderStatusWhenWaitingId = $this->model_setting_setting->getSettingValue("config_order_status_id");
        }

        if (empty($orderStatusWhenWaitingId)) {
            $orderStatusWhenWaitingId = $this->findOrderStatusIdByName(["Pendente", "Pending"], 1);
        }

        $settings["payment_{$group}_order_status_when_waiting_id"] = $orderStatusWhenWaitingId;

        // Use "processing" as default paid status.
        // On OpenCart installer, the processing status ID is 2:
        // https://github.com/opencart/opencart/blob/e3ae482e66671167b44f86e798f07f8084561117/upload/install/opencart.sql#L1568
        if (empty($settings["payment_{$group}_order_status_when_paid_id"])) {
            $settings["payment_{$group}_order_status_when_paid_id"] = $this->findOrderStatusIdByName(["Processando", "Processing"], 2);
        }

        if (empty($settings["payment_{$group}_payment_method_title"])) {
            $settings["payment_{$group}_payment_method_title"] = $methodTitle;
        }

        if (empty($settings["payment_{$group}_status"])) {
            $settings["payment_{$group}_status"] = "0";
        }

        if (empty($settings["payment_{$group}_notify_customer"])) {
            $settings["payment_{$group}_notify_customer"] = "0";
        }

        $this->model_setting_setting->editSetting("payment_{$group}", $settings);
    }

    /**
     * Looks for an ID that matches one of the names in the array or returns
     * the default ID.
     *
     * @param array<string> $possibleNames
     */
    private function findOrderStatusIdByName(array $possibleNames, int $defaultId): int
    {
        foreach ($possibleNames as $possibleName) {
            $result = $this->db->query("
                SELECT order_status_id FROM `" . DB_PREFIX . "order_status`
                WHERE `name` LIKE '" . $possibleName . "'
                ORDER BY `order_status_id` DESC
            ");

            if (! empty($result->row["order_status_id"])) {
                return $result->row["order_status_id"];
            }
        }

        return $defaultId;
    }

    /**
     * Create woovi orders table.
     *
     * The table is used to relate a OpenPix charge to an OpenCart order.
     */
    private function createWooviOrderTable(): void
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
    private function installEvents(): void
    {
        $this->load->model("setting/event");

        $this->model_setting_event->deleteEventByCode("woovi_catalog_view_account_order_info_before");
        $this->model_setting_event->deleteEventByCode("woovi_catalog_view_common_success_before");
        $this->model_setting_event->deleteEventByCode("woovi_catalog_controller_checkout_success_before");

        $this->model_setting_event->addEvent(
            "woovi_catalog_view_account_order_info_before",
            "catalog/view/account/order_info/before",
            "extension/woovi/events/handleCatalogViewAccountOrderInfoBeforeEvent",
        );

        $this->model_setting_event->addEvent(
            "woovi_catalog_controller_checkout_success_before",
            "catalog/controller/checkout/success/before",
            "extension/woovi/events/handleCatalogControllerCheckoutSuccessBeforeEvent"
        );

        $this->model_setting_event->addEvent(
            "woovi_catalog_view_common_success_before",
            "catalog/view/common/success/before",
            "extension/woovi/events/handleCatalogViewCommonSuccessBeforeEvent"
        );
    }

    /**
     * Uninstall the extension's event listeners in the OpenCart database.
     */
    private function uninstallEvents(): void
    {
        $this->load->model("setting/event");

        $this->model_setting_event->deleteEventByCode("woovi_catalog_view_common_success_before");
        $this->model_setting_event->deleteEventByCode("woovi_catalog_controller_checkout_success_before");
    }

    /**
     * Run upgrades.
     */
    private function upgrade(): void
    {
        $this->load->model("setting/extension");

        // Store latest upgraded version.
        $currentVersion = self::CURRENT_VERSION;

        $this->model_setting_setting->editSetting("thirdparty_payment_woovi", [
            "thirdparty_payment_woovi_latest_upgrade" => $currentVersion,
        ]);
    }
}
