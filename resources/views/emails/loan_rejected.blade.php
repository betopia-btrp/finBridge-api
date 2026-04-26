<h2>Loan Rejected</h2>

<p>Hello {{ $application->user_name }},</p>

<p>We regret to inform you that your loan application was not approved.</p>

<ul>
    <li>ID: {{ $application->id }}</li>
</ul>

<p>You may apply again in the future.</p>

<p>FinBridge Team</p>