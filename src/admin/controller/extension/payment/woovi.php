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
     * The code of payment method.
     */
    public const METHOD_CODE = "woovi";

    /**
     * Prefix of config keys.
     */
    public const CONFIG_PREFIX = "payment_" . self::METHOD_CODE;

    /**
     * Available setting keys.
     */
    public const FILLABLE_SETTINGS = [
        self::CONFIG_PREFIX . "_status",
        self::CONFIG_PREFIX . "_app_id",
        self::CONFIG_PREFIX . "_order_status_when_waiting_id",
        self::CONFIG_PREFIX . "_order_status_when_paid_id",
        self::CONFIG_PREFIX . "_notify_customer",
        self::CONFIG_PREFIX . "_tax_id_custom_field_id",
        self::CONFIG_PREFIX . "_payment_method_title",
    ];

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

        $settings = $this->getCurrentSettings();

        $this->response->setOutput($this->load->view("extension/payment/woovi", [
            "breadcrumbs" => $this->makeBreadcrumbs($marketplaceLink),

            // Alerts
            "woovi_warning" => $saveResult["warning"] ?? null,
            "woovi_success" => $saveResult["success"] ?? null,

            // Urls
            "woovi_register_account_url" => "https://app.woovi.com/register",
            "woovi_webhook_callback_url" => $wooviWebhookCallbackUrl,
            "woovi_opencart_documentation_url" => "https://developers.woovi.com/docs/ecommerce/opencart/opencart3-extension",

            // Routes
            "save_route" => $this->url->link("extension/payment/woovi", $tokenQuery),
            "previous_route" => $marketplaceLink,
            "create_custom_field_route" => $this->url->link("customer/custom_field", $tokenQuery),

            "order_statuses" => $orderStatuses,
            "custom_fields" => $customFields,

            // Components
            "components" => $this->makeSettingsPageComponents(),

            // Transalations
            "lang" => $this->language->all(),
        ] + $settings));
    }

    /**
     * Get available settings, from request or config table.
     *
     * @return array<array-key, mixed>
     */
    private function getCurrentSettings(): array
    {
        $settings = [];

        foreach (self::FILLABLE_SETTINGS as $settingName) {
            $settings[$settingName] = $this->getConfig($settingName);
        }

        return $settings;
    }

    /**
     * Get an config key from request or config table.
     *
     * @return mixed
     */
    private function getConfig(string $key)
    {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        return $this->config->get($key);
    }

    /**
     * Save settings from HTTP POSTed payload.
     *
     * @return array{success?: string, warning?: string}
     */
    private function save(): array
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            return [];
        }

        $this->load->language("extension/payment/woovi");

        if (! $this->user->hasPermission("modify", "extension/extension/payment")) {
            return ["warning" => "Warning: You do not have permission to modify Pix settings!"];
        }

        $this->load->model("setting/setting");

        $updatedSettings = array_filter(
            $this->request->post,
            fn (string $key) => in_array($key, self::FILLABLE_SETTINGS),
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
