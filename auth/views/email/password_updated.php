<p>
    Your password has been changed, if you made this request you can safely ignore this email.
</p>
<p>
    The request was made at {{updatedAt}}{{#updatedBy}} by <strong>{{updatedBy}}</strong>{{#updatedBy}}{{#ipAddress}} from IP address <strong>{{ipAddress}}</strong>{{#ipAddress}}.
</p>
<p>
    If it was not you who made this change, or you didn't request it, please <strong>immediately</strong>
    <?=anchor('auth/forgotten_password', 'reset your password')?> using the forgotten password facility and
    please let us know of any fraudulent activity on your account.
</p>