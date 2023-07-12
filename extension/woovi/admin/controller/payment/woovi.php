<?php

namespace Opencart\Admin\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * Settings page for Woovi extension.
 *
 * @property \Opencart\System\Engine\Loader $load
 * @property \Opencart\System\Library\Document $document
 * @property \Opencart\System\Library\Session $session
 * @property \Opencart\System\Library\Url $url
 * @property \Opencart\System\Library\Request $request
 * @property \Opencart\System\Library\Response $response
 * @property \Opencart\System\Library\Language $language
 * @property \Opencart\System\Engine\Config $config
 * @property \Opencart\System\Library\Cart\User $user
 * @property \Opencart\Admin\Model\Customer\CustomField $model_customer_custom_field
 * @property \Opencart\Admin\Model\Localisation\OrderStatus $model_localisation_order_status
 * @property \Opencart\Admin\Model\Setting\Setting $model_setting_setting
 * @property \Opencart\Admin\Model\Extension\Woovi\Payment\Woovi $model_extension_woovi_payment_woovi
 */
class Woovi extends Controller
{
    /**
     * Show or save settings.
     */
    public function index(): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        $this->document->setTitle($this->language->get("heading_title"));

        $tokenQuery = http_build_query([
            "user_token" => $this->session->data["user_token"],
        ]);

        $marketplaceLink = $this->url->link(
            "marketplace/extension",
            http_build_query([
                "user_token" => $this->session->data["user_token"],
                "type" => "module",
            ])
        );

        $this->load->model("localisation/order_status");
        $this->load->model("customer/custom_field");

        $orderStatuses = $this->model_localisation_order_status->getOrderStatuses();
        $customFields = $this->model_customer_custom_field->getCustomFields([
            "filter_status" => true
        ]);

        $this->response->setOutput($this->load->view("extension/woovi/payment/woovi", [
            "breadcrumbs" => $this->makeBreadcrumbs($marketplaceLink),

            // Routes
            "save_route" => $this->url->link("extension/woovi/payment/woovi|save", $tokenQuery),
            "previous_route" => $marketplaceLink,
            "create_custom_field_route" => $this->url->link("customer/custom_field", $tokenQuery),

            // Settings
            "payment_woovi_status" => $this->config->get("payment_woovi_status"),
            "payment_woovi_app_id" => $this->config->get("payment_woovi_app_id"),
            "payment_woovi_order_status_when_waiting_id" => $this->config->get("payment_woovi_order_status_when_waiting_id"),
            "payment_woovi_order_status_when_paid_id" => $this->config->get("payment_woovi_order_status_when_paid_id"),
            "payment_woovi_notify_customer" => $this->config->get("payment_woovi_notify_customer"),
            "payment_woovi_tax_id_custom_field_id" => $this->config->get("payment_woovi_tax_id_custom_field_id"),

            "order_statuses" => $orderStatuses,
            "custom_fields" => $customFields,

            // Components
            "components" => $this->makeSettingsPageComponents(),

            // Transalations
            "lang" => $this->language->all(),
        ]));
    }

    /**
     * Save settings from HTTP POSTed payload.
     */
    public function save(): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        if (! $this->user->hasPermission("modify", "extension/woovi/payment/woovi")) {
            $this->emitJson([
                "error" => [
                    "warning" => $this->language->get("Warning: You do not have permission to modify Pix settings!"),
                ],
            ]);
            return;
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

        $this->emitJson([
            "success" => $this->language->get("Success: You have modified Pix settings!"),
        ]);
    }

    /**
     * Run installation.
     */
    public function install(): void
    {
        if ($this->user->hasPermission("modify", "extension/payment")) {
            $this->load->model("extension/woovi/payment/woovi");
            $this->model_extension_woovi_payment_woovi->install();
        }
    }

    /**
     * Run uninstallation.
     */
    public function uninstall(): void
    {
        if ($this->user->hasPermission("modify", "extension/payment")) {
            $this->load->model("extension/woovi/payment/woovi");
            $this->model_extension_woovi_payment_woovi->uninstall();
        }
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
