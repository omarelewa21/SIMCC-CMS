<!DOCTYPE html>
<html>

<head>
    <title>Participants Verification Email</title>
</head>

<body>
    <h1>Hello,</h1>
    <p>This is a verification email for the competition {{ $competition->name }}. you need to verify your organization
        participants data before the deadline.</p>
    <p>The verification deadline is {{ $competition->verification_deadline }}.</p>
    <p>Thank you for participating!</p>
</body>

</html>
