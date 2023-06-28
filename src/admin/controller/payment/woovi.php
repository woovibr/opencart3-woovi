<?php

namespace Opencart\Admin\Controller\Extension\Woovi\Payment;

use Opencart\System\Engine\Controller;

class Woovi extends Controller
{
    public function index(): void
    {
        $this->load->language("extension/woovi/payment/woovi");

        $this->document->setTitle($this->language->get("document_title"));

        $marketplaceLink = $this->url->link(
            "marketplace/extension",
            http_build_query([
                "user_token" => $this->session->data["user_token"],
                "type" => "module",
            ])
        );

        $this->response->setOutput($this->load->view("extension/woovi/payment/woovi", [
            "breadcrumbs" => [
                [
                    "text" => $this->language->get("text_home"),
                    "href" => $this->url->link(
                        "common/dashboard",
                        http_build_query(["user_token" => $this->session->data["user_token"]]),
                ],
                [
                    "text" => $this->language->get("text_extension"),
                    "href" => $marketplaceLink,
                ],
                [
                    "text" => $this->language->get("document_title"),
                    "href" => $this->url->link(
                        "extension/woovi/payment/woovi",

                        http_build_query(
                            [
                                "user_token" => $this->session->data["user_token"],
                            ]

                            + isset($this->request->get["module_id"])
                                ? ["module_id" => $this->request->get["module_id"]]
                                : []
                        ),
                ],
            ],

            "save" => $this->url->link(
                "extension/woovi/payment/woovi.save",
                http_build_query([
                    "user_token" => $this->session->data["user_token"],
                ])
            ),
            "back" => $marketplaceLink,

            "module_woovi_status" => $this->config->get("module_woovi_status"),

            "header" =>  $this->load->controller("common/header"),
            "column_left" => $this->load->controller("common/column_left"),
            "footer" => $this->load->controller("common/footer"),
        ]));
    }

    public function save()
    {
        $this->load->language("extension/woovi/payment/woovi");
    }
}
