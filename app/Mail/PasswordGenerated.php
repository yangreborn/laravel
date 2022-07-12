<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public $subject = '度量平台密码重置';
    protected $data;

    /**
     * Create a new message instance.
     *
     * @param mixed $data 传入用户数据
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.password.generated')
            ->with([
                'password' => $this->data['password'],
            ]);
    }
}
