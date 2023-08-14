<?php

/**
 * An base settings controller for Pix and Parcelado payment methods.
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
 * 
 * @phpstan-type ActionArgs array{key: string}
 * @phpstan-type SaveResult array{success?: string, warning?: string}
 */
class ControllerExtensionWooviBaseConfig extends Controller
{
    /**
     * Fillable settings.
     */
    public const FILLABLE_SETTINGS = [
        "status",
        "app_id",
        "order_status_when_waiting_id",
        "order_status_when_paid_id",
        "notify_customer",
        "tax_id_custom_field_id",
        "payment_method_title",
    ];

    /**
     * Shared settings for all payment methods.
     */
    public const SHARED_SETTINGS = [
        "app_id",
    ];

    /**
     * Payment method key for Pix.
     */
    public const PIX_METHOD_KEY = "woovi";

    /**
     * Payment method KEY for Parcelado.
     */
    public const PARCELADO_METHOD_KEY = "woovi_parcelado";

    /**
     * Available payment methods keys.
     */
    public const AVAILABLE_METHOD_KEYS = [
        self::PIX_METHOD_KEY,
        self::PARCELADO_METHOD_KEY,
    ];

    /**
     * Load dependencies.
     */
    protected function load(string $methodKey): void
    {
        $this->load->language("extension/payment/" . $methodKey);
        $this->load->model("localisation/order_status");
        $this->load->model("customer/custom_field");
        $this->load->model("setting/setting");
    }

    /**
     * Show or save settings.
     */
    public function index(array $args): void
    {
        $this->validateActionArgs($args);

        $this->load($args["key"]);

        $saveResult = [];
        $httpPayload = $this->getHttpPostPayload();

        if ($this->isHttpPost()) {
            $saveResult = $this->save($args["key"], $httpPayload);
        }        

        $viewData = $this->prepareViewData($args, $saveResult, $httpPayload);

        $this->response->setOutput(
            $this->load->view(
                "extension/woovi/base_config",
                $viewData
            )
        );
    }

    /**
     * Prepare view data.
     * 
     * @param ActionArgs $args
     * @param SaveResult $saveResult
     * @param array<mixed> $httpPayload
     * @return array<string, string>
     */
    protected function prepareViewData(array $args, array $saveResult, array $httpPayload): array
    {
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
        $orderStatuses = $this->model_localisation_order_status->getOrderStatuses();
        $customFields = $this->model_customer_custom_field->getCustomFields([
            "filter_status" => true
        ]);
        $wooviWebhookCallbackUrl = $this->getWebhookCallbackUrl();
        $settings = $this->getCurrentSettings($args["key"], $httpPayload);

        return [
            // Payment method
            "payment_method_key" => $args["key"],

            // Breadcrumbs
            "breadcrumbs" => $this->makeBreadcrumbs($marketplaceLink),

            // Alerts
            "woovi_warning" => $saveResult["warning"] ?? null,
            "woovi_success" => $saveResult["success"] ?? null,

            // URLs
            "woovi_register_account_url" => "https://app.woovi.com/register",
            "woovi_webhook_callback_url" => $wooviWebhookCallbackUrl,
            "woovi_opencart_documentation_url" => "https://developers.woovi.com/docs/ecommerce/opencart/opencart3-extension",

            // Routes
            "save_route" => $this->url->link(
                "extension/payment/" . $args["key"],
                $tokenQuery
            ),
            "previous_route" => $marketplaceLink,
            "create_custom_field_route" => $this->url->link("customer/custom_field", $tokenQuery),

            "order_statuses" => $orderStatuses,
            "custom_fields" => $customFields,

            // Components
            "components" => $this->makeSettingsPageComponents(),

            // Transalations
            "lang" => $this->language->all(),

            // Settings
            "settings" => $settings,
        ];
    }

    /**
     * Get webhook callback URL.
     */
    protected function getWebhookCallbackUrl(): string
    {
        return str_replace(
            HTTP_SERVER,
            HTTP_CATALOG,
            $this->url->link("extension/woovi/payment/woovi_webhooks")
        );
    }

    /**
     * Get available settings, from request or config table.
     *
     * @param array $httpPayload
     * @return array<array-key, mixed>
     */
    protected function getCurrentSettings(string $methodKey, array $httpPayload): array
    {
        $settings = [];

        foreach (self::FILLABLE_SETTINGS as $settingName) {
            $configPrefix = $this->getConfigPrefix($methodKey, $settingName);
            $configKey = $configPrefix . "_" . $settingName;

            $settings[$settingName] = $this->getCurrentSetting($settingName, $configKey, $httpPayload);
        }

        return $settings;
    }

    /**
     * Get current setting from HTTP POSTed data or config table.
     */
    protected function getCurrentSetting(string $requestKey, string $configKey, array $httpPayload): ?string
    {
        if (isset($httpPayload["settings"][$requestKey])) {
            return strval($httpPayload["settings"][$requestKey]);
        }

        return strval($this->config->get($configKey));
    }

    /**
     * Get OpenCart's config prefix for an setting name.
     */
    protected function getConfigPrefix(string $methodKey, string $settingName): string
    {
        if (in_array($settingName, self::SHARED_SETTINGS)) {
            $methodKey = self::PIX_METHOD_KEY;
        }

        return "payment_" . $methodKey;    
    }

    /**
     * Save settings from HTTP POSTed payload.
     *
     * @param ActionArgs $args
     * @param array<mixed> $httpPayload
     * @return SaveResult
     */
    protected function save(string $methodKey, array $httpPayload): array
    {   
        $validationResult = $this->validateSaveRequest();

        if (! empty($validationResult)) {
            return $validationResult;
        }

        $updatedSettings = $this->getUpdatedSettings($httpPayload);

        $this->updateSettings($methodKey, $updatedSettings);

        return [
            "success" => $this->language->get("Success: You have modified Pix settings!"),
        ];
    }

    /**
     * Validate save request.
     * 
     * @return SaveResult
     */
    protected function validateSaveRequest(): array
    {
        $canUserModifyExtension = $this->user->hasPermission("modify", "extension/extension/payment");

        if (! $canUserModifyExtension) {
            return ["warning" => "Warning: You do not have permission to modify Pix settings!"];
        }

        return [];
    }

    /**
     * Get updated settings from HTTP POSTed data.
     * 
     * @param array<mixed> $httpPayload
     * @return array<mixed>
     */
    protected function getUpdatedSettings(array $httpPayload): array
    {
        $settingsFromRequest = $httpPayload["settings"] ?? [];

        if (! is_array($settingsFromRequest)) {
            $settingsFromRequest = [];
        }

        // Get only setting keys.
        $settings = array_filter(
            $settingsFromRequest,
            fn (string $key) => in_array($key, self::FILLABLE_SETTINGS),
            ARRAY_FILTER_USE_KEY
        );

        return $settings;
    }

    /**
     * Update settings.
     * 
     * @param array<mixed> $settings
     */
    protected function updateSettings(string $methodKey, array $settings): void
    {
        $settingGroups = [];

        foreach ($settings as $settingName => $settingValue) {
            $configPrefix = $this->getConfigPrefix($methodKey, $settingName);
            $configKey = $configPrefix . "_" . $settingName;

            $settingGroups[$configPrefix][$configKey] = $settingValue;
        }

        foreach ($settingGroups as $configPrefix => $settings) {
            $this->model_setting_setting->editSetting(
                $configPrefix,
                $settings,
            );
        }
    }

    /**
     * Run installation.
     * 
     * @param ActionArgs $args
     */
    public function install(array $args): void
    {
        $this->validateActionArgs($args);

        if ($this->user->hasPermission("modify", "extension/extension/payment")) {
            $this->load->model("extension/payment/woovi");
            $this->model_extension_payment_woovi->install();
        }
    }

    /**
     * Run uninstallation.
     * 
     * @param ActionArgs $args
     */
    public function uninstall(array $args): void
    {
        $this->validateActionArgs($args);

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
    protected function makeBreadcrumbs(string $marketplaceLink): array
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
    protected function makeSettingsPageComponents(): array
    {
        return [
            "header" =>  $this->load->controller("common/header"),
            "column_left" => $this->load->controller("common/column_left"),
            "footer" => $this->load->controller("common/footer"),
        ];
    }

    /**
     * Check if current request method is HTTP POST.
     */
    protected function isHttpPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === "POST";
    }

    /**
     * Get HTTP POSTed payload.
     * 
     * @return array<mixed>
     */
    protected function getHttpPostPayload(): array
    {
        /** @var array<mixed> $httpPayload */
        $httpPayload = $this->request->post;

        return $httpPayload;
    }

    /**
     * Check if action args is valid.
     * 
     * @param array<mixed> $args
     * 
     * @phpstan-assert ActionArgs $args
     */
    protected function validateActionArgs(array $args): void
    {
        $isKeyNonExistent = ! in_array($args["key"] ?? "", self::AVAILABLE_METHOD_KEYS);

        if ($isKeyNonExistent) {
            die();
        }
    }
}
