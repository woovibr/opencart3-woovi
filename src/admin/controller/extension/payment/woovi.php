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
 * @property Woovi\Opencart\Extension $woovi_extension
 *
 * @phpstan-type SaveResult array{success?: string, warning?: string}
 */
class ControllerExtensionPaymentWoovi extends Controller
{
    /**
     * Fillable settings for a payment method.
     */
    public const PAYMENT_METHOD_FILLABLE_SETTINGS = [
        "status",
        "order_status_when_waiting_id",
        "order_status_when_paid_id",
        "notify_customer",
        "payment_method_title",
    ];

    /**
     * Fillable settings.
     */
    public const FILLABLE_SETTINGS = [
        "woovi" => [
            "app_id",
            "tax_id_custom_field_id",
            "address_number_custom_field_id",
            "address_complement_custom_field_id",
            ...self::PAYMENT_METHOD_FILLABLE_SETTINGS,
        ],

        "woovi_parcelado" => self::PAYMENT_METHOD_FILLABLE_SETTINGS,
    ];

    /**
     * Load dependencies.
     */
    private function load(): void
    {
        $this->load->language("extension/payment/woovi");
        $this->load->model("localisation/order_status");
        $this->load->model("customer/custom_field");
        $this->load->model("setting/setting");
    }

    /**
     * Show or save settings.
     */
    public function index(): void
    {
        $this->load();

        $httpPayload = $this->getHttpPostPayload();

        $saveResult = [];

        if ($this->isHttpPost()) {
            $saveResult = $this->save($httpPayload);
        }

        $viewData = $this->prepareViewData($saveResult, $httpPayload);

        $this->response->setOutput(
            $this->load->view("extension/payment/woovi", $viewData)
        );
    }

    /**
     * Configure the extension using one click.
     */
    public function oneclick()
    {
        $this->load();
        $this->load->library("woovi");

        // Redirect to admin panel home page if not POST.
        if (! $this->isHttpPost()) {
            header("Location: " . HTTP_SERVER);
            exit;
        }

        // Validate if user has permission to modify extension.
        $validationResult = $this->validateSaveRequest();

        if (! empty($validationResult)) {
            $this->emitJson($validationResult);
            return;
        }

        // Remove current AppID.
        $this->removeCurrentAppID();

        // Redirect to the platform.
        $platformOneclickPageUrl = $this->getPlatformOneclickPageUrl();

        $this->emitJson([
            "redirect_url" => $platformOneclickPageUrl,
        ]);
    }

    /**
     * Prepare view data.
     *
     * @param SaveResult $saveResult
     * @param array<mixed> $httpPayload
     * @return array<string, string>
     */
    private function prepareViewData(array $saveResult, array $httpPayload): array
    {
        $this->document->setTitle($this->language->get("heading_title"));

        $lang = $this->language->all();

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

        $wooviWebhookCallbackUrl = $this->getWebhookCallbackUrl();

        $settings = $this->getCurrentSettings($httpPayload);
        $customFields = $this->model_customer_custom_field->getCustomFields([
            "filter_status" => true
        ]);

        $components = $this->makeSettingsPageComponents($settings, $lang);

        return [
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
                "extension/payment/woovi",
                $tokenQuery
            ),
            "previous_route" => $marketplaceLink,
            "create_custom_field_route" => $this->url->link("customer/custom_field", $tokenQuery),
            "oneclick_configuration_route" => $this->url->link(
                "extension/payment/woovi/oneclick",
                $tokenQuery
            ),

            // Components
            "components" => $components,

            // Translations
            "lang" => $lang,

            // Settings
            "settings" => $settings,

            // Custom fields
            "custom_fields" => $customFields,
        ];
    }

    /**
     * Get available settings, from request or config table.
     *
     * @param array<mixed> $httpPayload The available HTTP POST payload.
     * @return array<mixed>
     */
    private function getCurrentSettings(array $httpPayload): array
    {
        $settings = [];

        foreach (self::FILLABLE_SETTINGS as $groupName => $group) {
            foreach ($group as $settingName) {
                $configKey = "payment_" . $groupName . "_" . $settingName;

                if (isset($httpPayload["settings"][$groupName][$settingName])) {
                    $setting = $httpPayload["settings"][$groupName][$settingName];
                } else {
                    $setting = $this->config->get($configKey);
                }

                $settings[$groupName][$settingName] = $setting;
            }
        }

        return $settings;
    }

    /**
     * Save settings from HTTP POSTed payload.
     *
     * @param array<mixed> $httpPayload
     * @return SaveResult
     */
    private function save(array $httpPayload): array
    {
        $validationResult = $this->validateSaveRequest();

        if (! empty($validationResult)) {
            return $validationResult;
        }

        $updatedSettings = $this->getUpdatedSettings($httpPayload);

        $this->updateSettings($updatedSettings);

        return [
            "success" => $this->language->get("Success: You have modified Pix settings!"),
        ];
    }

    /**
     * Validate save request.
     *
     * @return SaveResult
     */
    private function validateSaveRequest(): array
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
    private function getUpdatedSettings(array $httpPayload): array
    {
        $settingsFromRequest = $httpPayload["settings"] ?? [];

        if (! is_array($settingsFromRequest)) {
            $settingsFromRequest = [];
        }

        // Get only fillable settings.
        $settings = $this->filterArrayByKeys($settingsFromRequest, array_keys(self::FILLABLE_SETTINGS));

        $normalizedSettings = [];

        // Normalize and filter settings.
        foreach ($settings as $settingName => $settingValue) {
            $fillableSettings = self::FILLABLE_SETTINGS[$settingName];

            $settingName = "payment_" . $settingName;

            if (is_array($fillableSettings)) {
                if (! is_array($settingValue)) {
                    $settingValue = [];
                }

                $nestedSettings = $this->filterArrayByKeys($settingValue, $fillableSettings);

                $normalizedNestedSettings = [];

                foreach ($nestedSettings as $nestedSettingName => $nestedSettingValue) {
                    $nestedSettingName = $settingName . "_" . $nestedSettingName;
                    $normalizedNestedSettings[$nestedSettingName] = $nestedSettingValue;
                }

                $settingValue = $normalizedNestedSettings;
            }

            $normalizedSettings[$settingName] = $settingValue;
        }

        return $normalizedSettings;
    }

    /**
     * Update settings.
     *
     * @param array<mixed> $settings
     */
    private function updateSettings(array $settings): void
    {
        foreach ($settings as $configPrefix => $settings) {
            $this->model_setting_setting->editSetting(
                $configPrefix,
                $settings,
            );
        }
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
                    "extension/payment/woovi",
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
     * @param array<mixed> $settings
     * @param array<mixed> $lang
     * @return array{header: string|mixed, column_left: string|mixed, footer: string|mixed}
     */
    private function makeSettingsPageComponents(array $settings, array $lang): array
    {
        $orderStatuses = $this->model_localisation_order_status->getOrderStatuses();

        $paymentMethodViewData = [
            "settings" => $settings,
            "lang" => $lang,
            "order_statuses" => $orderStatuses,
        ];

        $pixSettings = $this->load->view("extension/woovi/settings/payment_method", [
            "lang_panel_heading_title" => $this->language->get("Edit Pix Settings"),
            "setting_group" => "woovi",
        ] + $paymentMethodViewData);

        $parceladoSettings = $this->load->view("extension/woovi/settings/payment_method", [
            "lang_panel_heading_title" => $this->language->get("Edit Woovi Parcelado Settings"),
            "setting_group" => "woovi_parcelado",
        ] + $paymentMethodViewData);

        return [
            "header" =>  $this->load->controller("common/header"),
            "column_left" => $this->load->controller("common/column_left"),
            "footer" => $this->load->controller("common/footer"),

            "pix_payment_method_settings" => $pixSettings,
            "woovi_parcelado_payment_method_settings" => $parceladoSettings,
        ];
    }

    /**
     * Remove current App ID.
     */
    private function removeCurrentAppID(): void
    {
        $settings = $this->model_setting_setting->getSetting("payment_woovi");

        $settings["payment_woovi_app_id"] = "";

        $this->model_setting_setting->editSetting("payment_woovi", $settings);
    }

    /**
     * Get webhook callback URL.
     */
    private function getWebhookCallbackUrl(): string
    {
        return str_replace(
            HTTP_SERVER,
            HTTP_CATALOG,
            $this->url->link("extension/woovi/payment/woovi_webhooks")
        );
    }

    /**
     * Get one click configuration page URL at platform.
     */
    private function getPlatformOneclickPageUrl(): string
    {
        $wooviWebhookCallbackUrl = $this->getWebhookCallbackUrl();
        $platformUrl = $this->woovi_extension->getEnvironment()["platformUrl"] ?? "https://app.woovi.com";
        $opencartUrl = $platformUrl . "/home/applications/opencart3/add";

        return $opencartUrl . "?website=" . $wooviWebhookCallbackUrl;
    }

    /**
     * Check if current request method is HTTP POST.
     */
    private function isHttpPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === "POST";
    }

    /**
     * Get HTTP POSTed payload.
     *
     * @return array<mixed>
     */
    private function getHttpPostPayload(): array
    {
        /** @var array<mixed> $httpPayload */
        $httpPayload = $this->request->post;

        return $httpPayload;
    }

    /**
     * Get only specified values from array.
     *
     * @param array<mixed> $arr
     * @param array<mixed> $keys
     * @return array<mixed>
     */
    private function filterArrayByKeys(array $arr, array $keys): array
    {
        return array_filter(
            $arr,
            fn ($key) => in_array($key, $keys),
            ARRAY_FILTER_USE_KEY
        );
    }

     /**
     * Encode given data as JSON and emit it with correct `Content-type`.
     *
     * @param mixed $data
     */
    private function emitJson($data): void
    {
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput((string) json_encode($data));
    }
}
