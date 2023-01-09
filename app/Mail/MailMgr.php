<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailMgr extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $body, $email)
    {
        $this->title = $title;
        $this->body = $body;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {   


        if ($this->title == "お問い合わせ") {
            return $this->to($this->email)
            ->cc('info@toyoit.jp')
            ->subject($this->title)
            ->text('mail')
            ->with([
                'body' => $this->body,
            ]);
        } else {
            return $this->to($this->email)
            ->subject($this->title)
            ->text('mail')
            ->with([
                'body' => $this->body,
            ]);
        }
    }
}
