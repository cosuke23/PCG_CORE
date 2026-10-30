<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload 201 Files</title>

    {{-- Was @vite(...), which needs an npm build that was never deployed (HTTP 500). --}}
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #1e3a8a, #1e40af);
        }

        .container {
            max-width: 1100px;
            margin: auto;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        h1 {
            text-align: center;
            margin-bottom: 5px;
        }

        p.subtitle {
            text-align: center;
            opacity: 0.8;
            margin-bottom: 25px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .doc-box {
            background: rgba(255, 255, 255, 0.08);
            border: 1px dashed rgba(255, 255, 255, 0.3);
            padding: 15px;
            border-radius: 14px;
        }

        .doc-box label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
        }

        .doc-box input {
            width: 100%;
            color: white;
        }

        .uploaded {
            border: 1px solid #22c55e !important;
        }

        .success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid #22c55e;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-blue {
            background: #2563eb;
            color: white;
        }

        .btn-blue:hover {
            background: #1d4ed8;
        }

        .btn-gray {
            background: #6b7280;
            color: white;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="card">

            {{-- BACK BUTTON --}}
            <div class="btn-row">
                <a href="/profile" class="btn btn-gray">← Back</a>
            </div>

            <div class="header">
                <h1>Upload 201 Files</h1>
                <p class="subtitle">Complete your personnel document requirements</p>
            </div>

            {{-- SUCCESS --}}
            @if (session('success'))
                <div class="success">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ERRORS --}}
            @if ($errors->any())
                <div class="error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- DOCUMENT STATUS --}}
            @if (!empty($hasDocuments))
                <div class="success" style="text-align:center;">
                    ✔ You already uploaded 201 documents
                </div>
            @else
                <div class="error" style="text-align:center;">
                    ⚠ No documents uploaded yet
                </div>
            @endif

            @php
                $categories = [
                    'soi' => 'A. Latest SOI',
                    'appointment' => 'B. Appointment',
                    'leave' => 'C. Leave Balance',
                    'awards' => 'D. Awards',
                    'disciplinary' => 'E. Disciplinary Action',
                    'performance' => 'F. Performance Record',
                    'legal' => 'G. Legal Documents',
                    'academic' => 'H. Academic Records',
                    'financial' => 'I. Financial Record',
                    'retirement' => 'J. Retirement Record',
                    'body_picture' => 'K. Whole Body Picture',
                ];
            @endphp

            <form action="{{ route('upload.201.store') }}" method="POST" enctype="multipart/form-data">

                @csrf

                <div class="grid">

                    @foreach ($categories as $key => $label)
                        <div class="doc-box {{ !empty($documents->$key) ? 'uploaded' : '' }}">

                            <label>{{ $label }}</label>

                            {{-- EXISTING FILE --}}
                            @if (!empty($documents->$key))
                                <div style="margin-bottom:8px;">
                                    <small style="color:#22c55e;">
                                        ✔ Uploaded:
                                        <a href="{{ \App\Support\Uploads::url($documents->$key) }}" target="_blank"
                                            style="color:#22c55e;">
                                            View File
                                        </a>
                                    </small>
                                </div>
                            @endif

                            <input type="file" name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png">

                        </div>
                    @endforeach

                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-blue">
                        Submit All Documents
                    </button>
                </div>

            </form>

        </div>

    </div>

</body>

</html>
