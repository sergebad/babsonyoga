<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Socialize;
use View;

// created by Serge on July 13, 2017

class SocialAuthController extends Controller {
    public function redirect()
    {
        return Socialize::driver('facebook')->redirect();
    }

    public function callback(Request $request) {
        // when facebook calls us a with token
        $fbId = session('fbId');
        $callbackUri = session('callbackUri');
        if (!isset($fbId)) {
          // get data from FB
          $providerUser = Socialize::driver('facebook')->user();
          if (!is_null($providerUser)) {
            session(['fbId' => $providerUser->getId()]);
            session(['fbName' => $providerUser->getName()]);
            session(['fbNickname' => $providerUser->getNickname()]);
            session(['fbEmail' => $providerUser->getEmail()]);
            session(['fbAvatar' => $providerUser->getAvatar()]);
            // separate first and last name, thanks FB
            $fbNameArray = explode(' ', $providerUser->getName());
            if (count($fbNameArray) > 1) {
              session(['fbFirstName' => $fbNameArray[0]]);
              session(['fbLastName' => $fbNameArray[1]]);
            }
          } else {
            abort(504, 'Authentication failed.');
          }
        }
        if (isset($callbackUri)) {
          return new RedirectResponse($callbackUri);
        } else {
          // print_r($request->session()->all());
          $url = "https://graph.facebook.com/v2.6/me/messages?access_token=EAAMYqTayXyoBAK65fq07QQdiZACCh9LRmY93NTlOZClW0Ovn0TQrWIpKFQEGXlYVGslsZCf3XwM3w7373rKSixDTPT6mJiXEvmHyzZB4R6lb0pmJEqwxzsDpPM293uxmxeJxghXkmkXw2V8LuhvJCCiVSsEkYGxvaa3hMAIBNWxWom5YrXB2";
          $content = '{"recipient": {"phone_number": "+1(631)512-3249"}, "message": {"text": "hello from babsonyoga.com!"}}';
          echo "<p>$content</p>";
          $curl = curl_init($url);
          curl_setopt($curl, CURLOPT_HEADER, false);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
          curl_setopt($curl, CURLOPT_POST, true);
          curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
          $json_response = curl_exec($curl);
          $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
          if ( $status != 201 ) {
              die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
          }
          curl_close($curl);
          $response = json_decode($json_response, true);
          print_r($response);
          echo "<p>user logged in: " . session('fbName') . "</p>";
        }
    }

    public function privacy() {
        return View::make('Public.Babson.Privacy');
    }
}
