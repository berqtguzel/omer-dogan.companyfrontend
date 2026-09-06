<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data, private readonly ?array $settings = null)
    {
        $this->data = $data;
    }

    public function build()
    {
        $mail = $this->view('emails.contact')
            ->subject('Neue Kontaktanfrage von '.preg_replace('/[\r\n]+/', ' ', mb_substr((string) $this->data['name'], 0, 255)))
            ->with([
                'name' => $this->data['name'],
                'company' => $this->data['company'] ?? null,
                'email' => $this->data['email'],
                'phone' => $this->data['phone'] ?? null,
                'serviceType' => $this->data['serviceType'] ?? null,
                'messageText' => $this->data['message'],
                'brand' => MailBranding::resolve($this->settings),
            ]);

        if (filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($this->data['email'], $this->data['name']);
        }

        return $mail;
    }
}
