<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CivicPulse helps communities report and track local infrastructure problems.">
    <title>{{ $title }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        header {
            padding: 22px 8%;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        .brand {
            font-size: 24px;
            font-weight: 700;
            color: #166534;
        }

        main {
            width: min(900px, 88%);
            margin: 100px auto;
        }

        .hero {
            padding: 56px 42px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.06);
        }

        .hero h1 {
            margin-top: 0;
            font-size: clamp(32px, 5vw, 48px);
            line-height: 1.15;
        }

        .hero p {
            max-width: 650px;
            color: #64748b;
            font-size: 18px;
            line-height: 1.7;
        }

        .status {
            display: inline-block;
            margin-top: 18px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 14px;
            font-weight: 600;
        }

        footer {
            padding: 24px 8%;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>

<body>
    @include('partials/header')

    <main>
        @yield('content')
    </main>

    @include('partials/footer', [
    'year' => $year,
    ])
</body>

</html>