<?php

namespace Opencart\Admin\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

/**
 * Settings page for Woovi extension.
 */
class Woovi extends Controller
{
    /**
     * Show or savesettings.
     */
    public function index(): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        $this->document->setTitle($this->language->get("heading_title"));

        $marketplaceLink = $this->url->link(
            "marketplace/extension",
            http_build_query([
                "user_token" => $this->session->data["user_token"],
                "type" => "module",
            ])
        );

        $this->load->model("localisation/order_status");

        $this->response->setOutput($this->load->view("extension/woovi/payment/woovi", [
            "breadcrumbs" => $this->makeBreadcrumbs($marketplaceLink),

            // Routes
            "save_route" => $this->url->link(
                "extension/woovi/payment/woovi|save",
                http_build_query([
                    "user_token" => $this->session->data["user_token"],
                ])
            ),
            "previous_route" => $marketplaceLink,

            // Settings
            "payment_woovi_status" => $this->config->get("payment_woovi_status"),
            "payment_woovi_app_id" => $this->config->get("payment_woovi_app_id"),
            "payment_woovi_sort_order" => $this->config->get("payment_woovi_sort_order"),
            "payment_woovi_order_status_id" => $this->config->get("payment_woovi_order_status_id"),

            "order_statuses" => $this->model_localisation_order_status->getOrderStatuses(),

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
                    "warning" => $this->language->get("woovi_permission_error"),
                ],
            ]);
            return;
        }

        $this->load->model("setting/setting");

        $fillableSettings = [
            "payment_woovi_status",
            "payment_woovi_app_id",
            "payment_woovi_sort_order",
            "payment_woovi_order_status_id",
        ];

        $updatedSettings = array_filter(
            $this->request->post,
            fn (string $key) => in_array($key, $fillableSettings),
            ARRAY_FILTER_USE_KEY
        );

        $this->model_setting_setting->editSetting("payment_woovi", $updatedSettings);

        $this->emitJson([
            "success" => $this->language->get("woovi_updated_settings_message"),
        ]);
    }

    /**
     * Run installation.
     */
    public function install()
    {
        // TODO: Check if user can modify this extension.

        $this->load->model("extension/woovi/payment/woovi");
        $this->model_extension_woovi_payment_woovi->install();
    }

    /**
     * Run uninstallation.
     */
    public function uninstall()
    {
        // TODO: Check if user can modify this extension.

        $this->load->model("extension/woovi/payment/woovi");
        $this->model_extension_woovi_payment_woovi->uninstall();
    }

    /**
     * Encode given data as JSON and emit it with correct `Content-type`.
     *
     * @param mixed $data
     */
    private function emitJson($data): void
    {
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Make breadcrumbs for settings page.
     */
    private function makeBreadcrumbs(string $marketplaceLink): array
    {
        return [
            [
                "text" => $this->language->get("woovi_home_breadcrumb"),
                "href" => $this->url->link(
                    "common/dashboard",
                    http_build_query(["user_token" => $this->session->data["user_token"]]),
                ),
            ],
            [
                "text" => $this->language->get("woovi_extensions_breadcrumb"),
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
