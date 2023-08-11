<?php

/**
 * Settings page for Woovi extension.
 *
 * @property Loader $load
 * @property Document $document
 * @property Session $session
 * @property Url $url
 * @property Request $request
 * @property Response $response
 * @property Language $language
 * @property Config $config
 * @property Cart\User $user
 * @property ModelCustomerCustomField $model_customer_custom_field
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelSettingSetting $model_setting_setting
 * @property ModelExtensionPaymentWoovi $model_extension_payment_woovi
 */
class ControllerExtensionPaymentWoovi extends Controller
{
    /**
     * Show or save settings.
     */
    public function index(): void
    {
        $saveResult = $this->save();

        $this->load->language("extension/payment/woovi");

        $this->document->setTitle($this->language->get("heading_title"));

        $tokenQuery = http_build_query([
            "user_token" => $this->session->data["user_token"],
        ]);

        $marketplaceLink = $this->url->link(
            "marketplace/extension",
            http_build_query([
                "user_token" => $this->session->data["user_token"],
                "type" => "payment",
            ])
        );

        $this->load->model("localisation/order_status");
        $this->load->model("customer/custom_field");

        $orderStatuses = $this->model_localisation_order_status->getOrderStatuses();
        $customFields = $this->model_customer_custom_field->getCustomFields([
            "filter_status" => true
        ]);

        $wooviWebhookCallbackUrl = str_replace(
            HTTP_SERVER,
            HTTP_CATALOG,
            $this->url->link("extension/woovi/payment/woovi_webhooks")
        );

        $this->response->setOutput($this->load->view("extension/payment/woovi", [
            "breadcrumbs" => $this->makeBreadcrumbs($marketplaceLink),

            // Alerts
            "woovi_warning" => $saveResult["warning"] ?? null,
            "woovi_success" => $saveResult["success"] ?? null,

            // Urls
            "woovi_register_account_url" => "https://app.woovi.com/register",
            "woovi_webhook_callback_url" => $wooviWebhookCallbackUrl,
            "woovi_opencart_documentation_url" => "https://developers.woovi.com/docs/ecommerce/opencart/opencart-plugin#instale-o-plugin-woovi-na-sua-inst%C3%A2ncia-opencart-utilizando-one-click",

            // Routes
            "save_route" => $this->url->link("extension/payment/woovi", $tokenQuery),
            "previous_route" => $marketplaceLink,
            "create_custom_field_route" => $this->url->link("customer/custom_field", $tokenQuery),

            // Settings
            "payment_woovi_status" => $this->getConfig("payment_woovi_status"),
            "payment_woovi_app_id" => $this->getConfig("payment_woovi_app_id"),
            "payment_woovi_order_status_when_waiting_id" => $this->getConfig("payment_woovi_order_status_when_waiting_id"),
            "payment_woovi_order_status_when_paid_id" => $this->getConfig("payment_woovi_order_status_when_paid_id"),
            "payment_woovi_notify_customer" => $this->getConfig("payment_woovi_notify_customer"),
            "payment_woovi_tax_id_custom_field_id" => $this->getConfig("payment_woovi_tax_id_custom_field_id"),

            "order_statuses" => $orderStatuses,
            "custom_fields" => $customFields,

            // Components
            "components" => $this->makeSettingsPageComponents(),

            // Transalations
            "lang" => $this->language->all(),
        ]));
    }

    /**
     * Get an config key from request or config table.
     *
     * @return mixed
     */
    private function getConfig(string $key)
    {
        if (isset($this->request->post[$key])) return $this->request->post[$key];

        return $this->config->get($key);
    }

    /**
     * Save settings from HTTP POSTed payload.
     *
     * @return array{success?: string, warning?: string}
     */
    private function save(): array
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") return [];

        $this->load->language("extension/payment/woovi");

        if (! $this->user->hasPermission("modify", "extension/extension/payment")) {
            return ["warning" => "Warning: You do not have permission to modify Pix settings!"];
        }

        $this->load->model("setting/setting");

        $fillableSettings = [
            "payment_woovi_status",
            "payment_woovi_app_id",
            "payment_woovi_order_status_when_waiting_id",
            "payment_woovi_order_status_when_paid_id",
            "payment_woovi_notify_customer",
            "payment_woovi_tax_id_custom_field_id",
        ];

        $updatedSettings = array_filter(
            $this->request->post,
            fn (string $key) => in_array($key, $fillableSettings),
            ARRAY_FILTER_USE_KEY
        );

        $this->model_setting_setting->editSetting("payment_woovi", $updatedSettings);

        return [
            "success" => $this->language->get("Success: You have modified Pix settings!"),
        ];
    }

    /**
     * Run installation.
     */
    public function install(): void
    {
        if ($this->user->hasPermission("modify", "extension/extension/payment")) {
            $this->load->model("extension/payment/woovi");
            $this->model_extension_payment_woovi->install();
        }
    }

    /**
     * Run uninstallation.
     */
    public function uninstall(): void
    {
        if ($this->user->hasPermission("modify", "extension/extension/payment")) {
            $this->load->model("extension/payment/woovi");
            $this->model_extension_payment_woovi->uninstall();
        }
    }

    /**
     * Make breadcrumbs for settings page.
     *
     * @return array<array{text: string, href: string}>
     */
    private function makeBreadcrumbs(string $marketplaceLink): array
    {
        return [
            [
                "text" => $this->language->get("Home"),
                "href" => $this->url->link(
                    "common/dashboard",
                    http_build_query(["user_token" => $this->session->data["user_token"]]),
                ),
            ],
            [
                "text" => $this->language->get("Extensions"),
                "href" => $marketplaceLink,
            ],
            [
                "text" => $this->language->get("heading_title"),
                "href" => $this->url->link(
                    "extension/woovi/payment/woovi",

                    http_build_query(
                        [
                            "user_token" => $this->session->data["user_token"],
                            "module_id" => $this->request->get["module_id"] ?? null
                        ]
                    ),
                ),
            ],
        ];
    }

    /**
     * Render components for settings page.
     *
     * @return array{header: string|mixed, column_left: string|mixed, footer: string|mixed}
     */
    private function makeSettingsPageComponents(): array
    {
        return [
            "header" =>  $this->load->controller("common/header"),
            "column_left" => $this->load->controller("common/column_left"),
            "footer" => $this->load->controller("common/footer"),
        ];
    }
}
