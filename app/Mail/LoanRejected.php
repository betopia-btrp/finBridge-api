<?php

namespace App\Mail;


use Illuminate\Mail\Mailable;


class LoanRejected extends Mailable
{
    public $application;

    public function __construct($application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this->subject('Loan Rejected')
            ->view('emails.loan_rejected')
            ->with(['application' => $this->application]);
    }
}
