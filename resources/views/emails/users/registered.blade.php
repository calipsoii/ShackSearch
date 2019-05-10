@component('mail::message')
# New User Registration

{{ $user->name }} has logged into nullterminated.org.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
