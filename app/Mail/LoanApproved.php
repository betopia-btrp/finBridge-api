<?php

namespace App\Mail;


use Illuminate\Mail\Mailable;


class LoanApproved extends Mailable
{
    public $application;

    public function __construct($application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this->subject('Loan Approved')
            ->view('emails.loan_approved')
            ->with(['application' => $this->application]);
    }
}
