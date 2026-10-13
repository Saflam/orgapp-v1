<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your event pass</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222; line-height: 1.5;">
    <h1>Your event pass is ready</h1>

    <p>You have a pass for <strong>{{ $event->title }}</strong>.</p>

    @if ($event->starts_at)
        <p><strong>Starts:</strong> {{ $event->starts_at->timezone($event->timezone ?? config('app.timezone'))->format('M j, Y g:i A') }}</p>
    @endif

    @if ($event->ends_at)
        <p><strong>Ends:</strong> {{ $event->ends_at->timezone($event->timezone ?? config('app.timezone'))->format('M j, Y g:i A') }}</p>
    @endif

    <p>Present this QR code at event check-in. Keep it private; it is a bearer credential for your pass.</p>

    <div style="margin: 24px 0;">
        {!! $qrSvg !!}
    </div>

    <p>If the QR code cannot be scanned, contact the event organizer. Do not share this email or QR code with anyone else.</p>
</body>
</html>
