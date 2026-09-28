<?php

namespace App\Mail;

use App\Models\OffboardingProcess;
use Illuminate\Mail\Mailable;

class RhProcessMail extends Mailable
{
    public function __construct(
        public OffboardingProcess $process,
        public string $heading,
        public string $bodyText,
    ) {}

    public function build(): self
    {
        return $this->subject($this->heading)->html(nl2br(e($this->bodyText)));
    }
}
