<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>OSAERT Certificates</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .certificate {
            width: 297mm;
            height: 210mm;
            margin: 20px auto;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
            border: 12px solid #0033A0;
            padding: 12px;
        }

        .certificate:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .certificate-inner {
            width: 100%;
            height: 100%;
            border: 2px solid #d4af37;
            position: relative;
            padding: 35px 55px;
        }

        .corner {
            position: absolute;
            width: 55px;
            height: 55px;
            border-color: #0033A0;
        }

        .corner.top-left {
            top: 12px;
            left: 12px;
            border-top: 5px solid;
            border-left: 5px solid;
        }

        .corner.top-right {
            top: 12px;
            right: 12px;
            border-top: 5px solid;
            border-right: 5px solid;
        }

        .corner.bottom-left {
            bottom: 12px;
            left: 12px;
            border-bottom: 5px solid;
            border-left: 5px solid;
        }

        .corner.bottom-right {
            bottom: 12px;
            right: 12px;
            border-bottom: 5px solid;
            border-right: 5px solid;
        }

        .certificate-header {
            text-align: center;
        }

        .pcg-title {
            font-size: 16px;
            font-weight: 700;
            color: #0033A0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .osaert-title {
            font-size: 27px;
            font-weight: 800;
            color: #0033A0;
            letter-spacing: 4px;
            margin-top: 4px;
        }

        .certificate-title {
            margin-top: 22px;
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .presented {
            margin-top: 15px;
            font-size: 15px;
            color: #6b7280;
            font-style: italic;
        }

        .trainee-name {
            margin-top: 8px;
            font-size: 31px;
            font-weight: 800;
            color: #0033A0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .name-line {
            width: 60%;
            margin: 5px auto 12px;
            border-bottom: 2px solid #d4af37;
        }

        .company {
            font-size: 17px;
            font-weight: 600;
            color: #374151;
        }

        .completion-text {
            margin: 18px auto 0;
            max-width: 760px;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
        }

        .training-name {
            font-weight: 800;
            color: #0033A0;
        }

        .dates {
            display: flex;
            justify-content: center;
            gap: 70px;
            margin-top: 18px;
        }

        .date-item {
            text-align: center;
        }

        .date-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .date-value {
            margin-top: 4px;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        .certificate-footer {
            position: absolute;
            left: 55px;
            right: 55px;
            bottom: 32px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }

        .signature {
            width: 220px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #374151;
            margin-bottom: 6px;
        }

        .signature-name {
            font-size: 13px;
            font-weight: 700;
        }

        .signature-title {
            font-size: 11px;
            color: #6b7280;
        }

        .qr-section {
            text-align: center;
            width: 120px;
        }

        .qr-code {
            width: 90px;
            height: 90px;
            margin: 0 auto 4px;
        }

        .qr-code img,
        .qr-code canvas {
            width: 90px !important;
            height: 90px !important;
            display: block;
        }

        .qr-label {
            font-size: 9px;
            color: #6b7280;
            line-height: 1.2;
        }

        .certificate-number {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 10px;
            color: #6b7280;
            font-weight: 600;
        }

        .action-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            padding: 15px 20px;
            border-bottom: 1px solid #d1d5db;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .print-button {
            background: #7e22ce;
        }

        .print-button:hover {
            background: #6b21a8;
        }

        .close-button {
            background: #4b5563;
        }

        .close-button:hover {
            background: #374151;
        }

        .action-button i {
            margin-right: 8px;
        }

        @media print {
            html,
            body {
                width: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .no-print {
                display: none !important;
            }

            .certificate {
                width: 297mm;
                height: 210mm;
                margin: 0 !important;
                box-shadow: none !important;
                page-break-after: always;
                break-after: page;
            }

            .certificate:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <div class="action-bar">

            <button type="button"
                    onclick="window.print()"
                    class="action-button print-button">
                <i class="fas fa-print"></i>
                Print / Save as PDF
            </button>

            <button type="button"
                    onclick="window.close()"
                    class="action-button close-button">
                <i class="fas fa-times"></i>
                Close
            </button>

        </div>
    </div>

    @forelse($trainees as $trainee)

        @php
            $year = substr(trim((string) $trainee->date_ended), -4);

            $certificateNumber = 'OSAERT-' . $year . '-' .
                str_pad($trainee->id, 6, '0', STR_PAD_LEFT);

            $verificationUrl = url('/osaert/verify/' . $trainee->id);
        @endphp

        <div class="certificate">

            <div class="certificate-inner">

                <div class="corner top-left"></div>
                <div class="corner top-right"></div>
                <div class="corner bottom-left"></div>
                <div class="corner bottom-right"></div>

                <div class="certificate-number">
                    Certificate No. {{ $certificateNumber }}
                </div>

                <div class="certificate-header">

                    <div class="pcg-title">
                        Philippine Coast Guard
                    </div>

                    <div class="osaert-title">
                        OSAERT
                    </div>

                    <div class="certificate-title">
                        Certificate of Completion
                    </div>

                    <div class="presented">
                        This certificate is proudly presented to
                    </div>

                    <div class="trainee-name">
                        {{ $trainee->name }}
                    </div>

                    <div class="name-line"></div>

                    <div class="company">
                        {{ $trainee->company }}
                    </div>

                    <div class="completion-text">
                        for successfully completing the
                        <span class="training-name">
                            Oil Spill Awareness and Emergency Response Training
                        </span>
                        and fulfilling the requirements of the training program.
                    </div>

                    <div class="dates">

                        <div class="date-item">
                            <div class="date-label">
                                Date Started
                            </div>

                            <div class="date-value">
                                {{ $trainee->date_attended }}
                            </div>
                        </div>

                        <div class="date-item">
                            <div class="date-label">
                                Date Completed
                            </div>

                            <div class="date-value">
                                {{ $trainee->date_ended }}
                            </div>
                        </div>

                    </div>

                </div>

                <div class="certificate-footer">

                    <div class="signature">

                        <div style="height: 28px;"></div>

                        <div class="signature-line"></div>

                        <div class="signature-name">
                            AUTHORIZED SIGNATORY
                        </div>

                        <div class="signature-title">
                            Philippine Coast Guard
                        </div>

                    </div>

                    <div class="qr-section">

                        <div id="qrcode-{{ $trainee->id }}"
                             class="qr-code"
                             data-url="{{ $verificationUrl }}">
                        </div>

                        <div class="qr-label">
                            Scan to verify certificate
                        </div>

                    </div>

                </div>

            </div>

        </div>

    @empty

        <div style="padding: 50px; text-align: center; font-family: Arial, sans-serif;">
            <h2>No completed trainees found.</h2>
            <p>Please return to the OSAERT trainee list and apply another filter.</p>
        </div>

    @endforelse

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            document.querySelectorAll('.qr-code').forEach(function (element) {

                const verificationUrl = element.dataset.url;

                new QRCode(element, {
                    text: verificationUrl,
                    width: 90,
                    height: 90,
                    correctLevel: QRCode.CorrectLevel.H
                });

            });

        });
    </script>

</body>
</html>