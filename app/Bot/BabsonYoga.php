<?php
namespace App\Bot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\EventStats;
use App\Models\Ticket;
use App\Models\Attendee;
use App\Events\OrderCompletedEvent;
use Validator;
use Carbon\Carbon;
use App\Bot\Bot;

class BabsonYoga {
    public static $NEW_QUESTION = "new";
    public static $ANSWER = "answer";
    public static $CLASSES = "classes";
    public static $MAIN_MENU = "main menu";
    public static $REGISTER = "register";
    public static $CANCEL = "cancel";
    public static $PAYLOAD_ARG_DELIMITER = "&";
    public static $PAYLOAD_ARG_VAL_DELIMITER = "=";
    public static $TRANSPORTATION_OPTIONS = array(
                                                  0=>array('option'=>'I need a ride',
                                                        'response'=>'OK! We\'ll be in touch soon with transportation details.'),
                                                  1=>array('option'=>'Can drive others',
                                                        'response'=>'Thanks! We\'ll be in touch if we need your help driving people.'),
                                                  2=>array('option'=>'Other',
                                                        'response'=>'Great! We\'ll see you soon.')
                                                );
    public $question;
    public $options;
    private $solution;
    public static $STRIKES_THRESHOLD = 2; // if attendee gets that many, no more registrations

    public function __construct(array $data) {
      $this->question = $data["question"];
      $answer = $data["correct_answer"];
      $this->options = $data["incorrect_answers"];
      $this->options[] = $answer;
      shuffle($this->options); //shuffle the options, so we don't always present the right answer at a fixed place
      $this->solution = $answer;
    }

    public static function getNew() {
      //clear any past solutions left in the cache
      Cache::forget("solution");
      //make API call and decode result to get general-knowledge trivia question
      $ch = curl_init("https://opentdb.com/api.php?amount=1&category=9&type=multiple");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $result = json_decode(curl_exec($ch), true)["results"][0];
      if (empty($result)) {
       return "BabsonYoga.getNew error: Could not get new trivia question.";
      }
      return new BabsonYoga($result);
    }

    public function toMessage() {
      //compose message
      $response = "Question: $this->question.\nOptions:";
      $letters = ["a", "b", "c", "d"];
      foreach ($this->options as $i => $option) {
          $response.= "\n{$letters[$i]}: $option";
          if($this->solution == $option) {
             Cache::forever("solution", $letters[$i]);
          }
      }
      return ["text" => $response];
    }

    public static function checkAnswer($answer) {
      $solution = Cache::get("solution");
      if ($solution == strtolower($answer)) {
          $response = "Correct!";
      } else {
          $response = "Wrong. Correct answer is $solution";
      }
      //clear solution
      Cache::forget("solution");
      return $response;
    }

    public static function mainMenu($userProfile) {
      $message = array(
                        "attachment" => array(
                                              "type"=>"template",
                                              "payload"=>array(
                                                "template_type"=>"button",
                                                "text"=>"Hi, " . $userProfile->first_name . "!\nWhat would you like to do?",
                                                "buttons"=>array(array(
                                                    "type"=>"postback",
                                                    "title"=>"Register for a class",
                                                    "payload"=>"action" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . BabsonYoga::$CLASSES
                                                  ),
                                                  array(
                                                    "type"=>"postback",
                                                    "title"=>"Cancel registration",
                                                    "payload"=>"action" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . BabsonYoga::$CANCEL
                                                  )
                                                )
                                              )
                                        )
                );
      return json_encode($message);
    }

    public static function getFutureClasses() {
      $classes = Event::whereDate('start_date', '>=', date('Y-m-d'))->where('is_live', 1)->orderBy('start_date')->get();
      $classElements = array();
      $classElements[] = array("title"=>"Select a class to register",
                                "subtitle"=>"",
                                "image_url"=>"http://www.babsonyoga.com/public/assets/images/public/yoga_pattern.png");
      if (!count($classes)) {
        Log::info("BabsonYoga.getFutureClasses: no classes found.");
        return "BabsonYoga found no future classes.";
      }
      foreach ($classes as $class) {
        $classStart = BabsonYoga::dateFormat($class->start_date);
        $classEnd = BabsonYoga::timeFormat($class->end_date);
        $ticket = Ticket::where('event_id', $class->id)->firstOrFail();
        $ticket_quantity_remaining = ($ticket->quantity_remaining > 0) ? $ticket->quantity_remaining . " spots" : "Wait-list";
        $classElements[] = array(
                                // element object
                                "title"=>$class->title,
                                "subtitle"=>"$classStart - $classEnd at " . $class->venue_name . " ($ticket_quantity_remaining)" ,
                                "buttons"=>array(
                                  array(
                                    "title"=>"Register",
                                    "type"=>"postback",
                                    "payload"=>"action" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . BabsonYoga::$REGISTER .
                                                BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                                                "event_id" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $class->id .
                                                BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                                                "ticket_id" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $ticket->id
                                  )
                                )
                            );
      }
      $message = array(
                        "attachment" => array(
                                              "type"=>"template",
                                              "payload"=>array(
                                                "template_type"=>"list",
                                                "top_element_style"=>"compact",
                                                "elements"=>$classElements
                                              )
                                        )
                );
      //Log::info("getFutureClasses response: " . json_encode($message));
      return json_encode($message);
    }

    public static function register($event_id, $ticket_id, $userProfile) {
      if (empty($event_id)) {
        Log::error("BabsonYoga.register error: missing event_id.");
        return "There was an error with registration: missing event ID. Please contact the Messenger Administrator.";
      }
      if (empty($event_id)) {
        Log::error("BabsonYoga.register error: missing ticket_id.");
        return "There was an error with registration: missing ticket ID. Please contact the Messenger Administrator.";
      }
      if (empty($userProfile)) {
        Log::error("BabsonYoga.register error: missing user profile.");
        return "There was an error with registration: missing user profile. Please contact the Messenger Administrator.";
      }
      // check if the attendee is already registered for the event
      $attendee = Attendee::where('event_id', $event_id)
                  ->where('email', $userProfile->userId)
                  ->where('is_cancelled', 0)
                  ->first();
      if (!empty($attendee)) {
        return array('status'=>500, 'message'=>'You are already registered for this class.');
      }
      // check if the attendee has reached strikes threshold
      $strikes = DB::table('strikes')
                  ->where('attendee_identifier', $userProfile->userId)
                  ->whereDate('expires_on', '>', Carbon::now())
                  ->get();
      if (count($strikes) >= BabsonYoga::$STRIKES_THRESHOLD) {
        return array('status'=>500, 'message'=>'You have received ' . BabsonYoga::$STRIKES_THRESHOLD . ' strikes and your account has been locked.');
      }
      $event = Event::findOrFail($event_id);
      $ticket = Ticket::findOrFail($ticket_id);
      $ticket_quantity_remaining = $ticket->quantity_remaining;
      $order = new Order;
      $message = null;
      /*
       * No payment required so go ahead and complete the order
       */
       DB::beginTransaction();
       try {
           $order = new Order();
           $attendee_increment = 1;
           /*
            * Create the order
            */
           $order->first_name = $userProfile->first_name;
           $order->last_name = $userProfile->last_name;
           $order->email = $userProfile->userId;
           // no offline payment required
           $order->order_status_id = ($ticket_quantity_remaining > 0) ? config('attendize.order_complete') : config('attendize.order_waitlist');
           $order->amount = 0;
           $order->booking_fee = 0;
           $order->organiser_booking_fee = 0;
           $order->discount = 0.00;
           $order->account_id = $event->account->id;
           $order->event_id = $event_id;
           $order->is_payment_received = 1;
           $order->save();
           /*
            * Update the event sales volume
            */
           $event->increment('sales_volume', 0);
           $event->increment('organiser_fees_volume', 0);
           /*
            * Update the event stats
            */
           $event_stats = EventStats::firstOrNew([
               'event_id' => $event_id,
               'date'     => DB::raw('CURRENT_DATE'),
           ]);
           $event_stats->increment('tickets_sold', 1);
           /*
            * Update some ticket info
            */
           $ticket->increment('quantity_sold', 1);
           $ticket->increment('sales_volume', 0);
           $ticket->increment('organiser_fees_volume', 0);
           /*
            * Insert order item (for use in generating invoices)
            */
           $orderItem = new OrderItem();
           $orderItem->title = $ticket->title;
           $orderItem->quantity = 1;
           $orderItem->order_id = $order->id;
           $orderItem->unit_price = 0;
           $orderItem->unit_booking_fee = 0;
           $orderItem->save();
           /*
            * Create the attendee
            */
           $attendee = new Attendee();
           $attendee->first_name = $userProfile->first_name;
           $attendee->last_name = $userProfile->last_name;
           $attendee->email = $userProfile->userId;
           $attendee->event_id = $event_id;
           $attendee->order_id = $order->id;
           $attendee->ticket_id = $ticket->id;
           $attendee->account_id = $event->account->id;
           $attendee->reference_index = $attendee_increment;
           $attendee->save();
           /*
            * Queue up some tasks - Emails to be sent, PDFs etc.
            */
            event(new OrderCompletedEvent($order));
       } catch (Exception $e) {
           Log::error($e);
           DB::rollBack();
           return array('status'=>500, 'message'=>'There was an error with registration. Please contact the Messenger Administrator.');
       }
       DB::commit();
       if ($ticket_quantity_remaining > 0) {
         $status = array("status"=>200, "message"=>'You are registered! You will receive a reminder a day before your scheduled class.');
       } else {
         $status = array("status"=>400, "message"=>'You are on the waitlist. We will contact you if a spot opens up.');
       }
       return $status;
    }

    public static function getUserClasses($userProfile) {
      $orders = Order::join('attendees', 'attendees.order_id', '=', 'orders.id')
                        ->join('events', 'events.id', '=', 'attendees.event_id')
                        ->where('orders.email',$userProfile->userId)
                        ->where('orders.is_deleted', 0)
                        ->where('orders.is_cancelled', 0)
                        ->where('attendees.is_cancelled', 0)
                        ->where('events.start_date', '>', Carbon::now())
                        ->orderBy('orders.id', 'desc')
                        ->limit(3)
                        ->get();
      if (!count($orders)) {
        return "BabsonYoga found no registrations to cancel.";
      }
      $classElements = array();
      $classElements[] = array("title"=>"Which class would you like to cancel?",
                                "subtitle"=>"If you are cancelling less than 3 hours in advance, you will receive a strike.",
                                "image_url"=>"http://www.babsonyoga.com/public/assets/images/public/yoga_pattern.png");
      foreach($orders as $order) {
        $class = Event::where('id', $order->event_id)
                  ->whereDate('start_date', '>=', date('Y-m-d'))
                  ->where('is_live', 1)
                  ->first();
        if (empty($class)) {
          continue;
        }
        $classDate = date_create($class->start_date->toDateTimeString());
        $classDate = date_format($classDate, 'D n/j');
        $classElements[] = array(
                                // element object
                                "title"=>$class->title,
                                "subtitle"=>$classDate . " at " . $class->venue_name,
                                "buttons"=>array(
                                  array(
                                    "title"=>"Cancel " . $classDate,
                                    "type"=>"postback",
                                    "payload"=>"action" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . BabsonYoga::$CANCEL .
                                                BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                                                "event_id" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $class->id .
                                                BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                                                "order_id" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $order->order_id
                                  )
                                )
                            );
      }
      if (count($classElements) == 1) {
        // maximum of 4 elements and minimum of 2 elements required
        // https://developers.facebook.com/docs/messenger-platform/send-api-reference/list-template
        $classElements[] = array("title"=>"No eligible class registrations found.",
                                  "subtitle"=>"If you think this is an error, please contact the BabsonYoga administrator.");
      }
      $message = array(
                        "attachment" => array(
                                              "type"=>"template",
                                              "payload"=>array(
                                                "template_type"=>"list",
                                                "top_element_style"=>"large",
                                                "elements"=>$classElements
                                              )
                                        )
                );
      //Log::info("getUserClasses response: " . json_encode($message));
      return json_encode($message);
    }

    public static function cancelRegistration($userProfile, $order_id) {
      $attendees = Attendee::join('orders', 'orders.id', '=', 'attendees.order_id')
                            ->where('attendees.email',$userProfile->userId)
                            ->where('attendees.order_id', $order_id)
                            ->get();
      if ($attendees->count() == 0) {
        Log::error("BabsonYoga.cancelRegistration could not find attendee " . $userProfile->userId . " for order " . $order_id);
        return "BabsonYoga found nothing to cancel. It may have already been cancelled.";
      }
      foreach ($attendees as $attendee) {
        if ($attendee->order_status_id == config('attendize.order_complete')) {
          // decrement only if it was a registered user, not for wait-list
          $attendee->ticket->decrement('quantity_sold');
        }
        $attendee->is_cancelled = 1;
        $attendee->save();
        Log::info("BabsonYoga.cancelRegistration cancelled attendee " . $userProfile->userId . " for order " . $order_id);
        // Strike
        $isStrike = false;
        if ((Carbon::now() < $attendee->event->start_date) &&
            (Carbon::now()->addHours(3) > $attendee->event->start_date)) {
          // cancellation less than three hours of the event
          BabsonYoga::strike($attendee->email, 'Late cancellation');
          $isStrike = true;
        } elseif ((Carbon::now()->addHours(3) < $attendee->event->start_date) &&
                  ($attendee->order_status_id == config('attendize.order_complete'))) {
          // Find the earliest attendee with wait-list status
          $waitListAttendee = DB::table('attendees')->
                                join('orders', 'orders.id', '=', 'attendees.order_id')->
                                join('events', 'events.id', '=', 'orders.event_id')->
                                where('orders.order_status_id', config('attendize.order_waitlist'))->
                                where('attendees.is_cancelled', 0)->
                                where('orders.event_id', $attendee->event_id)->
                                orderBy('orders.created_at', 'asc')->
                                first();
          // Change status to registered
          if (!empty($waitListAttendee)) {
            DB::table('orders')->
              where('id', $waitListAttendee->order_id)->
              update(['order_status_id'=>config('attendize.order_complete')]);
            Log::info("Order " . $waitListAttendee->order_id . " promoted from waitlist to registered.");
            // Send notification to the attendee whose status was changed from wait-list to registered
            $classDate = BabsonYoga::dateFormat($waitListAttendee->start_date);
            BabsonYoga::sendNotification($waitListAttendee->email, "You're in the class!\nYou have been added to " . $classDate . " at " . $waitListAttendee->venue_name);
            BabsonYoga::getTransportationOptions($waitListAttendee->event_id);
          }
        }
      }
      if ($isStrike) {
        return "Cancellation confirmed. You have received a strike for late cancellation.";
      }
      return "Cancellation confirmed. We hope to see you next week.";
    }

    public static function getTransportationOptions ($event_id) {
      $buttons = array();
      foreach (BabsonYoga::$TRANSPORTATION_OPTIONS as $key=>$val) {
        $buttons[] = array(
          "type"=>"postback",
          "title"=>$val['option'],
          "payload"=>"action" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . BabsonYoga::$ANSWER .
                      BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                      "answer" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $key .
                      BabsonYoga::$PAYLOAD_ARG_DELIMITER .
                      "event_id" . BabsonYoga::$PAYLOAD_ARG_VAL_DELIMITER . $event_id
        );
      }
      $message = array(
                        "attachment" => array(
                                              "type"=>"template",
                                              "payload"=>array(
                                                "template_type"=>"button",
                                                "text"=>"How will you be getting to the class?",
                                                "buttons"=>$buttons
                                              )
                                        )
                );
      return json_encode($message);
    }

    public static function saveTransportationOption ($event_id, $qAnswer, $userProfile) {
      $orders = Order::where('email',$userProfile->userId)
                        ->where('orders.is_deleted', 0)
                        ->where('orders.is_cancelled', 0)
                        ->where('event_id', $event_id)
                        ->get();
      if (!count($orders)) {
        Log::error("BabsonYoga.saveTransportationOption error: order not found. Event ID " . $event_id . " email " . $userProfile->userId);
        return "BabsonYoga found no corresponding orders.";
      }
      foreach($orders as $order) {
        $order->notes = BabsonYoga::$TRANSPORTATION_OPTIONS[$qAnswer]['option'];
        $order->save();
      }
      return BabsonYoga::$TRANSPORTATION_OPTIONS[$qAnswer]['response'];
    }

    public static function reminders($hours = 24) {
      // find all attendees whose class starts in the next <arg> hours
      $attendees = Attendee::join('orders', 'orders.id', '=', 'attendees.order_id')
                    ->join('events', 'orders.event_id', '=', 'events.id')
                    ->where('attendees.is_cancelled', 0)
                    ->where('orders.order_status_id', config('attendize.order_complete'))
                    ->where('events.is_live', 1)
                    ->where('events.start_date', '>', Carbon::now())
                    ->where('events.start_date', '<', Carbon::now()->addHours($hours))
                    ->get();
      foreach ($attendees as $attendee) {
        // send notification
        $classDate = BabsonYoga::dateFormat($attendee->start_date);
        $message = "You are scheduled for " . $attendee->start_date . " " . $attendee->venue_name . "\n"
                    . "Please arrive a few minutes early to set up.\n"
                    . "Mats and towels are provided, but make sure to bring water.\n"
                    . "See you on the mat!";
        BabsonYoga::sendNotification($attendee->email, $message);
        // log
        Log::info("Reminder sent to " . $attendee->first_name . " " . $attendee->last_name . "(" . $attendee->email . ") for the " . $attendee->start_date . " " . $attendee->venue_name . " event.");
      }
    }

    public static function sendNotification($attendeeIdentifier, $message) {
      $data = ['text' => $message];
      Bot::sendMessage($attendeeIdentifier, $data);
    }

    public static function strike ($attendeeIdentifier, $description) {
      $strike = new Strike();
      $strike->attendee_identifier = $attendeeIdentifier;
      $strike->description = $description;
      $strike->save();
    }

    public static function dateFormat($d) {
      $d = date_create($d);
      $d = date_format($d, 'D n/j g:i A');
      return $d;
    }

    public static function timeFormat($d) {
      $d = date_create($d);
      $d = date_format($d, 'g:i A');
      return $d;
    }

    public static function authenticate($userProfile, $pass) {
      $visits = DB::table('visits')->where('attendee_identifier', $userProfile->userId)->get();
      if (count($visits)) {
        return true;
      } else {
        if (strcasecmp($pass, env('PASSPHRASE')) == 0) {
          // correct passphrase
          $visit = new Visit();
          $visit->attendee_identifier = $userProfile->userId;
          $visit->first_name = $userProfile->first_name;
          $visit->last_name = $userProfile->last_name;
          $visit->save();
          return true;
        }
      }
      return false;
    }

}
