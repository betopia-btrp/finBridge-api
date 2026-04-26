<h2>Loan Application Submitted</h2>

<p>Hello {{ $application->user_name }},</p>

<p>Your application has been received.</p>

<ul>
    <li>ID: {{ $application->id }}</li>
    <li>Amount: {{ $application->amount }}</li>
    <li>Duration: {{ $application->duration_months }} months</li>
    <li>Status: Pending</li>
</ul>


<p>We will notify you once your application is approved or rejected.</p>

<p>Thank you,<br>FinBridge Team</p>