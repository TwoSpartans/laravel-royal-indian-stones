<p>Hello {{ $recipientName }},</p>
<p>You are invited to the Royal Indian Stones staff portal as {{ $roleLabel }}.</p>
<p><a href="{{ $acceptUrl }}">Set your password</a></p>
<p>This link expires in {{ config('portal.invitation_days') }} days and works once. If you were not expecting it, ignore this email.</p>
