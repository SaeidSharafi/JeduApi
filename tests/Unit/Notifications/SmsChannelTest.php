<?php

use App\Models\User;
use App\Notifications\SmsChannel;

test('it calls the toSms method on the notification', function () {
    $channel = new SmsChannel();
    $notifiable = new User();

    $notificationWithMethod = new class extends \Illuminate\Notifications\Notification {
        public function toSms(object $notifiable): void {}
    };
    $spyNotification = spy($notificationWithMethod);

    $channel->send($notifiable, $spyNotification);


    $spyNotification->shouldHaveReceived('toSms')->with($notifiable)->once();
});
