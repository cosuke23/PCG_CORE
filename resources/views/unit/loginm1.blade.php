<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unit Login</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        /* VIDEO BACKGROUND */
        .video-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
        }

        /* DARK OVERLAY */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            z-index: -1;
        }

        /* CENTER CONTAINER */
        .container {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* LOGIN BOX */
        .login-box {
            width: 380px;
            padding: 35px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            color: white;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255,255,255,0.2);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            font-size: 13px;
            opacity: 0.9;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            margin-bottom: 15px;
            border: none;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #22c55e;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-size: 15px;
        }

        button:hover {
            background: #16a34a;
            transform: scale(1.02);
        }

        .error {
            background: #ef4444;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: center;
        }

        .logo {
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.85;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            opacity: 0.7;
        }
    </style>
</head>

<body>

<!-- VIDEO BACKGROUND -->
<video autoplay muted loop playsinline class="video-bg">
    <source src="{{ asset('backgrounds/web bg loging vid.mp4') }}" type="video/mp4">
</video>

<!-- OVERLAY -->
<div class="overlay"></div>

<!-- LOGIN -->
<div class="container">

    <div class="login-box">

        <div class="logo">
            🔐 M1 Document Checker
        </div>

        <h2>M1 Login</h2>

        {{-- ERROR MESSAGE --}}
        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="/unit/loginM1">
            @csrf

            <label>Email</label>
            <input type="email" name="email" placeholder="Enter email" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>

            <button type="submit">Login</button>
        </form>

        <div class="footer">
            Secure Access System
        </div>

    </div>

</div>

</body>
</html>
