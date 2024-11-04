<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Display</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .email-container {
            background: #fff;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .attachment {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-5">Email Contents</h1>

        @foreach ($emails as $email)
            <div class="email-container">
                <h2>{{ $email['subject'] }}</h2>
                <p><strong>From:</strong> {{ $email['from'] }}</p>
                <p><strong>To:</strong> {{ $email['to'] }}</p>
                <p><strong>CC:</strong> {{ $email['cc'] }}</p>
                <div class="email-body">
                    <strong>Body:</strong>
                    {!! $email['body'] !!}
                </div>
{{--                 
                @if (!empty($email['attachments']))
                    <div class="attachments">
                        <strong>Attachments:</strong>
                        <ul>
                            @foreach ($email['attachments'] as $attachment)
                                <li class="attachment">
                                    <a href="{{ $attachment['file_path'] }}" target="_blank">
                                        {{ $attachment['file_name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif --}}
            </div>
        @endforeach
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.7/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
