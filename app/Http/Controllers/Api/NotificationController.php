<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\User;
use Notification;
use App\Notifications\SendNotification;
use Illuminate\Support\Facades\DB;


class NotificationController extends Controller
{
    private $_userSchemaUser;

    public function sendOfferNotification() {

        $this->_userSchemaUser =  User::find(26);
        dd($this->_userSchemaUser->notifications);

        $offerData = [
            'name' => 'BOGO',
            'body' => 'You received an offer.',
            'thanks' => 'Thank you',
            'offerText' => 'Check out the offer',
            'offerUrl' => url('/'),
            'offer_id' => 007
        ];

        Notification::send($this->_userSchemaUser, new SendNotification($offerData));

        dd('Task completed!');
    }

    public function getPageUnreadNotification ($page) {

        $userUnreadNotification = DB::table('notifications')->where([
            ['notifiable_id', '=', auth()->user()->id],
            ['read_at', '=', null],
            ['data->data->page', '=', $page],
        ])->get(['id','data->data as data']);

        return response()->json([
            "status" => 200,
            "message" => $userUnreadNotification
        ]);
    }

    public function listAllUnreadNotification () {

        $userUnreadNotification = DB::table('notifications')->where([
            ['notifiable_id', '=', auth()->user()->id],
            ['read_at', '=', null],
        ])->get(['id','data->data as data']);

        return response()->json([
            "status" => 200,
            "message" => $userUnreadNotification
        ]);
    }
}
