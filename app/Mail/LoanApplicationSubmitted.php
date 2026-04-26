<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LoanApplicationSubmitted extends Mailable
{
    public $application;

    public function __construct($application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this->subject('Loan Application Submitted')
            ->view('emails.loan_application_submitted')
            ->with([
                'application' => $this->application
            ]);
    }
}
