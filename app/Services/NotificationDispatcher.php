<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client as TwilioClient;

/**
 * The notifications table row is always written first -- it's the source
 * of truth per the design doc. Delivery (Twilio/mail) is best-effort on
 * top of that row; a delivery failure never prevents the record existing.
 */
class NotificationDispatcher
{
    public function toStaff(User $user, string $title, string $body): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'title' => $title,
            'body' => $body,
            'sent_at' => now(),
        ]);

        NotificationCreated::dispatch($notification);

        return $notification;
    }

    public function toCustomer(Customer $customer, string $channel, string $title, string $body): Notification
    {
        $notification = Notification::create([
            'customer_id' => $customer->id,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
        ]);

        match ($channel) {
            'sms' => $this->sendSms($customer->phone, $body),
            'whatsapp' => $this->sendWhatsApp($customer->phone, $body),
            'email' => $this->sendEmail($customer->email, $title, $body),
            default => Log::warning("NotificationDispatcher: unknown channel [{$channel}]"),
        };

        $notification->update(['sent_at' => now()]);

        return $notification;
    }

    protected function sendSms(?string $to, string $body): void
    {
        if (! $to) {
            Log::info("Notification[sms] skipped -- customer has no phone number on file.");

            return;
        }

        if (! config('services.twilio.sid') || ! config('services.twilio.token')) {
            Log::info("Notification[sms] to {$to}: {$body}");

            return;
        }

        $this->twilioClient()->messages->create($to, [
            'from' => config('services.twilio.from_sms'),
            'body' => $body,
        ]);
    }

    protected function sendWhatsApp(?string $to, string $body): void
    {
        if (! $to) {
            Log::info("Notification[whatsapp] skipped -- customer has no phone number on file.");

            return;
        }

        if (! config('services.twilio.sid') || ! config('services.twilio.token')) {
            Log::info("Notification[whatsapp] to {$to}: {$body}");

            return;
        }

        $this->twilioClient()->messages->create("whatsapp:{$to}", [
            'from' => 'whatsapp:'.config('services.twilio.from_whatsapp'),
            'body' => $body,
        ]);
    }

    protected function sendEmail(?string $to, string $title, string $body): void
    {
        if (! $to) {
            Log::info("Notification[email] skipped -- customer has no email address on file.");

            return;
        }

        Mail::raw($body, fn ($message) => $message->to($to)->subject($title));
    }

    protected function twilioClient(): TwilioClient
    {
        return new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));
    }
}
