<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} | Vijay Aqua</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f8fb;
            color: #173047;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 6%;
            background: #fff;
            border-bottom: 1px solid #e2ebf0;
        }

        main {
            max-width: 900px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .card {
            padding: 32px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(18, 53, 70, .06);
        }

        button {
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #087f9b;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <header>
        <strong>Vijay Aqua</strong>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </header>

    <main>
        <div class="card">
            <h1>{{ $heading }}</h1>
            <p>Welcome, {{ auth()->user()->name }}.</p>
        </div>
    </main>
</body>

</html>
