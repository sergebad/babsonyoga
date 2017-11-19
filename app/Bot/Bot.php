<?php

// https://tutorials.botsfloor.com/building-a-facebook-messenger-trivia-bot-with-laravel-part-1-61209b0e35db

namespace App\Bot;

use App\Bot\Webhook\Messaging;
use Illuminate\Support\Facades\Log;

class Bot {
    protected $messaging;
    protected $userId;
    protected $userProfile;

  // constructor
  public function __construct(Messaging $messaging) {
      $this->messaging = $messaging;
      $this->userId = $this->messaging->getSenderId();
      //$this->getUserProfile();
  }

    public function getUserProfile() {
        if (empty($this->userProfile)) {
            if (empty($this->userId)) {
                Log::error('Bot.getUserProfile error: empty user ID.');
                throw new Exception('Bot.getUserProfile error: empty user ID.');
            }
            $url = 'https://graph.facebook.com/v2.6/'.$this->userId.'?fields=first_name,last_name,locale,timezone,gender&access_token='.env('PAGE_ACCESS_TOKEN');
            $timeout = 5;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $this->userProfile = json_decode(curl_exec($ch));
            $this->userProfile->userId = $this->userId;
            curl_close($ch);
        }
        //Log::info("User profile obtained from Facebook: " . $this->userProfile->first_name);
        return $this->userProfile;
    }

    public function extractDataFromMessage() {
        $matches = [];
        $text = $this->messaging->getMessage()->getText();
        //single letter message means an answer
        if (preg_match('/^(\\w)$/i', $text, $matches)) {
            return [
                'type' => BabsonYoga::$ANSWER,
                'data' => [
                    'answer' => $matches[0],
                    'text' => $text
                ],
            ];
        } elseif (preg_match('/^new|next$/i', $text, $matches)) {
            //"new" or "next" requests a new question
            return [
                'type' => BabsonYoga::$NEW_QUESTION,
                'data' => ['text' => $text],
            ];
        }
        // default
        return [
            'type' => 'other',
            'data' => ['text' => $text],
        ];
    }

    public function extractDataFromPostback() {
      $data = array();
        $payload = $this->messaging->getMessage()->getPayload();
        $payload = explode(BabsonYoga::$PAYLOAD_ARG_DELIMITER, $payload);
        foreach ($payload as $arg) {
          $temp = explode(BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER, $arg);
          if (isset($temp[1])) {
            $data[$temp[0]] = $temp[1];
          } else {
            $data[$temp[0]] = null;
          }
        }
        return (object) $data;
    }

    public function reply($data) {
        $recipientFirstName = $this->getUserProfile()->first_name;
        $id = $this->messaging->getSenderId();
        if (method_exists($data, 'toMessage')) {
          // trivia
          $data = $data->toMessage();
        } else {
          $message = json_decode($data);
          if (json_last_error() === JSON_ERROR_NONE) {
              // Facebook JSON
              $data = $message;
          } elseif (gettype($data) == 'string') {
              $data = ['text' => $data];
          }
        }
        Log::info("Sending reply to $recipientFirstName: " . json_encode($data));
        Bot::sendMessage($id, $data);
    }

    public static function sendMessage($recipientId, $message) {
        $messageData = [
          'recipient' => [
              'id' => $recipientId,
          ],
          'message' => $message,
        ];
        $ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token='.env('PAGE_ACCESS_TOKEN'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        //Log::info(print_r(curl_exec($ch), true));
        curl_exec($ch);
    }
}
