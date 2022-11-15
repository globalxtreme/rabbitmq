<?php

namespace GlobalXtreme\RabbitMQ\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FailedMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $subject
     * @param string $error
     * @param string $trace
     */
    public function __construct(string $subject, public string $error, public string $trace)
    {
        $this->subject("Message-Broker-Bug: $subject");
    }

    public function build()
    {
        return $this->html('
                            <p><b>Message: </b>' . $this->error . '</p>
                            <p><b>Trace: </b>' . $this->trace . '</p>
                            ');
    }
}
