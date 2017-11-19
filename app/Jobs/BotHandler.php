<?php

// https://tutorials.botsfloor.com/building-a-facebook-messenger-trivia-bot-with-laravel-part-1-61209b0e35db

namespace App\Jobs;

use App\Jobs\Job;
use App\Bot\BabsonYoga;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Bot\Webhook\Messaging;
use Illuminate\Support\Facades\Log;
use App\Bot\Bot;

class BotHandler extends Job implements ShouldQueue {
    use InteractsWithQueue, SerializesModels;
    protected $messaging;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Messaging $messaging) {
        $this->messaging = $messaging;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
      $bot = new Bot($this->messaging);
      if ($this->messaging->getType() == Messaging::$TYPE_MESSAGE) {
        $custom = $bot->extractDataFromMessage();
        switch ($custom["type"]) {
          case BabsonYoga::$NEW_QUESTION:
            // trivia question, no authentication required
            $bot->reply(BabsonYoga::getNew());
            break;
          case BabsonYoga::$ANSWER:
            // trivia answer, no authentication required
            $bot->reply(BabsonYoga::checkAnswer($custom["data"]["answer"]));
            break;
          default:
            // authenticate
            if (BabsonYoga::authenticate($bot->getUserProfile(), $custom["data"]["text"])) {
              $bot->reply(BabsonYoga::mainMenu($bot->getUserProfile()));
            } else {
              $bot->reply("Please enter the correct pass phrase.");
              return;
            }
        }
      } else if ($this->messaging->getType() == Messaging::$TYPE_POSTBACK) {
        $custom = $bot->extractDataFromPostback();
        if (!BabsonYoga::authenticate($bot->getUserProfile(), null)) {
          // fist time visitor
          $bot->reply("Please enter pass phrase.");
          return;
        }
        if (isset($custom->action)) {
          switch ($custom->action) {
            case BabsonYoga::$CLASSES:
              // show future classes
              $bot->reply(BabsonYoga::getFutureClasses());
              break;
            case BabsonYoga::$REGISTER:
              // register for class
              $status = BabsonYoga::register($custom->event_id, $custom->ticket_id, $bot->getUserProfile());
              if ($status['status'] == 200) {
                $bot->reply($status['message']);
                // successful registration, continue with transportation options
                $bot->reply(BabsonYoga::getTransportationOptions($custom->event_id));
              } else {
                $bot->reply($status['message']);
              }
              break;
            case $custom->action == BabsonYoga::$ANSWER:
              // record transportation option
              $bot->reply(BabsonYoga::saveTransportationOption($custom->event_id, $custom->answer, $bot->getUserProfile()));
              break;
            case BabsonYoga::$CANCEL:
              // cancel registration
              if (empty($custom->order_id)) {
                // list attendee's orders
                $bot->reply(BabsonYoga::getUserClasses($bot->getUserProfile()));
              } else {
                // cancel registration for the class
                $bot->reply(BabsonYoga::cancelRegistration($bot->getUserProfile(), $custom->order_id));
              }
              break;
          }
        } else {
          // default
          $bot->reply(BabsonYoga::mainMenu($bot->getUserProfile()));
        }
      }
    }
}
