<?php

namespace App\Bot\Webhook;

use Illuminate\Support\Facades\Log;

class Messaging {
    public static $TYPE_MESSAGE = "message";
    public static $TYPE_POSTBACK = "postback";
    private $senderId;
    private $recipientId;
    private $timestamp;
    private $message;
    private $type;

    public function __construct(array $data) {
        $this->senderId = $data["sender"]["id"];
        $this->recipientId = $data["recipient"]["id"];
        $this->timestamp = $data["timestamp"];
        if(isset($data["message"])) {
          $this->type = Messaging::$TYPE_MESSAGE;
          $this->message = new Message($data["message"]);
        } else if (isset($data["postback"])) {
          $this->type = Messaging::$TYPE_POSTBACK;
          $this->message = new Postback($data["postback"]);
        }
    }

    public function getSenderId()
    {
        return $this->senderId;
    }

    public function getRecipientId()
    {
        return $this->recipientId;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getType()
    {
        return $this->type;
    }

}
