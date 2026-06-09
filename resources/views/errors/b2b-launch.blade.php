<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B2B Launch Error</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111827; color: #f9fafb; margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .card { max-width: 560px; background: #1f2937; border-radius: 16px; padding: 28px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        .code { color: #fbbf24; font-weight: 700; margin-bottom: 10px; }
        .message { color: #d1d5db; line-height: 1.45; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">{{ $code }}</div>
        <div class="message">{{ $message }}</div>
    </div>
</body>
</html>
