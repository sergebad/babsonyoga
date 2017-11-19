<?php

namespace App\Bot\Webhook;


class Postback
{
    private $title;
    private $payload;
    private $referral;

    public function __construct(array $data) {
         $this->title = $data["title"];
         $this->payload = isset($data["payload"]) ? $data["payload"] : "";
         $this->referral = isset($data["referral"]) ? $data["referral"] : "";
    }

    public function getTitle() {
        return $this->title;
    }

    public function getPayload() {
        return $this->payload;
    }

    public function getReferral() {
        return $this->referral;
    }
}
