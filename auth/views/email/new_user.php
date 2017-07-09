{{#admin.id}}
<p>
    {{admin.first_name}} {{admin.last_name}}, an administrator for <?=APP_NAME?>, has just created a
    new <em>{{admin.group.name}}</em> account for you.
</p>
{{/admin.id}}
{{^admin.id}}
<p>
    Thank you for registering at the <?=APP_NAME?> website.
</p>
{{/admin.id}}
{{#password}}
<p>
    Your password is shown below.
    {{#temp_pw}}
    You will be asked to set this to something more memorable when you log in.
    {{/temp_pw}}
</p>
<p class="heads-up" style="font-weight:bold;font-size:1.5em;text-align:center;font-family:LucidaConsole, Monaco, monospace;">
    {{password}}
</p>
{{/password}}
<p>
    <?=anchor('auth/login', 'Click here to log in', 'class="btn"')?>
</p>
{{#verifyUrl}}
<hr/>
<p>
    Additionally, we would appreciate it if you could verify your email address by clicking the button below,
    we do this to maintain the integrity of our database.
</p>
<p>
    <a href="{{verifyUrl}}" class="btn small">Verify Email</a>
</p>
{{/verifyUrl}}
