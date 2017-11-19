<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Bot\BabsonYoga;

// created by Serge on July 13, 2017

class FBMessengerController extends Controller {
    // main callback function
    public function update(Request $request) {
      // verification
      if (($request->get('hub.mode') == 'subscribe') && ($request->get('hub_verify_token') == 'kimberlyfarms')) {
          Log::info('Verification request to FBMessengerController.update');
          echo $request->get('hub_challenge');
          return;
      }
      $entries = \App\Bot\Webhook\Entry::getEntries($request);
      foreach ($entries as $entry) {
          // Log::info('Messenger request to FBMessengerController.update');
          $messagings = $entry->getMessagings();
          foreach ($messagings as $messaging) {
            dispatch(new \App\Jobs\BotHandler($messaging));
          }
      }
      // http status 200
      return;
    }
    // controller command that invokes notifications
    public function notify(Request $request) {
      BabsonYoga::reminders();
      //Log::info('FBMessengerController.notify ran successfully on ' . date('Y-m-d H:i:s'));
    }
}
