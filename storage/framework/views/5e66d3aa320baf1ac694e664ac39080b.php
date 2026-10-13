<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your event pass</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222; line-height: 1.5;">
    <h1>Your event pass is ready</h1>

    <p>You have a pass for <strong><?php echo e($event->title); ?></strong>.</p>

    <?php if($event->starts_at): ?>
        <p><strong>Starts:</strong> <?php echo e($event->starts_at->timezone($event->timezone ?? config('app.timezone'))->format('M j, Y g:i A')); ?></p>
    <?php endif; ?>

    <?php if($event->ends_at): ?>
        <p><strong>Ends:</strong> <?php echo e($event->ends_at->timezone($event->timezone ?? config('app.timezone'))->format('M j, Y g:i A')); ?></p>
    <?php endif; ?>

    <p>Present this QR code at event check-in. Keep it private; it is a bearer credential for your pass.</p>

    <div style="margin: 24px 0;">
        <?php echo $qrSvg; ?>

    </div>

    <p>If the QR code cannot be scanned, contact the event organizer. Do not share this email or QR code with anyone else.</p>
</body>
</html>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Events\resources/views/emails/event-pass-issued.blade.php ENDPATH**/ ?>