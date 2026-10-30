<?php

namespace App\Http\Controllers;

use App\Models\OsaertTrainee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OsaertController extends Controller
{
    public function index(Request $request)
    {
        $startedYear = $request->get('started_year');
        $endedYear = $request->get('ended_year');

        $query = OsaertTrainee::query();

        if ($startedYear) {
            $query->whereYearOf('date_attended', $startedYear);
        }

        if ($endedYear) {
            $query->whereYearOf('date_ended', $endedYear);
        }

        $trainees = $query
            ->latest()
            ->get();

        $totalTrainees = OsaertTrainee::count();

        $completedTrainees = OsaertTrainee::whereNotNull('date_ended')
            ->where('date_ended', '!=', '')
            ->count();

        $completedFilteredTrainees = $trainees
            ->filter(function ($trainee) {
                return trim((string) $trainee->date_ended) !== '';
            })
            ->count();

        $startedYears = OsaertTrainee::whereNotNull('date_attended')
            ->where('date_attended', '!=', '')
            ->selectRaw(OsaertTrainee::yearSql('date_attended') . ' as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $endedYears = OsaertTrainee::whereNotNull('date_ended')
            ->where('date_ended', '!=', '')
            ->selectRaw(OsaertTrainee::yearSql('date_ended') . ' as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('admin.osaert.index', compact(
            'trainees',
            'totalTrainees',
            'completedTrainees',
            'completedFilteredTrainees',
            'startedYears',
            'endedYears',
            'startedYear',
            'endedYear'
        ));
    }

    public function template()
    {
        $headers = [
            'name',
            'company',
            'date_attended',
            'date_ended',
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');

            fputcsv($file, $headers);

            fputcsv($file, [
                'Juan Dela Cruz',
                'ABC Corporation',
                '20/09/2026',
                '22/09/2026',
            ]);

            fclose($file);
        };

        return response()->streamDownload(
            $callback,
            'osaert_bulk_upload_template.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ]);

        $file = fopen($request->file('file')->getRealPath(), 'r');

        if (!$file) {
            return back()->with('error', 'Unable to read uploaded file.');
        }

        $header = fgetcsv($file);

        $expectedHeaders = [
            'name',
            'company',
            'date_attended',
            'date_ended',
        ];

        $header = array_map(
            fn($value) => strtolower(trim($value ?? '')),
            $header ?? []
        );

        if ($header !== $expectedHeaders) {
            fclose($file);

            return back()->with(
                'error',
                'Invalid CSV headers. Please download and use the OSAERT template.'
            );
        }

        $rows = [];
        $rowNumber = 1;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            $rowNumber++;

            if (count($row) !== 4) {
                $errors[] = "Row {$rowNumber}: Invalid number of columns.";
                continue;
            }

            [$name, $company, $dateAttended, $dateEnded] = array_map(
                fn($value) => trim($value ?? ''),
                $row
            );

            if (
                $name === '' &&
                $company === '' &&
                $dateAttended === '' &&
                $dateEnded === ''
            ) {
                continue;
            }

            $validator = Validator::make([
                'name' => $name,
                'company' => $company,
                'date_attended' => $dateAttended ?: null,
                'date_ended' => $dateEnded ?: null,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'company' => ['required', 'string', 'max:255'],
                'date_attended' => [
                    'required',
                    'date_format:d/m/Y',
                ],
                'date_ended' => [
                    'required',
                    'date_format:d/m/Y',
                    'after_or_equal:date_attended',
                ],
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " .
                    implode(' ', $validator->errors()->all());

                continue;
            }

            $rows[] = [
                'name' => $name,
                'company' => $company,
                'date_attended' => Carbon::createFromFormat(
                    'd/m/Y',
                    $dateAttended
                )->format('Y-m-d'),
                'date_ended' => Carbon::createFromFormat(
                    'd/m/Y',
                    $dateEnded
                )->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($errors)) {
            return back()
                ->with('upload_errors', $errors)
                ->with(
                    'error',
                    'Upload cancelled. Please fix the CSV errors and try again.'
                );
        }

        if (empty($rows)) {
            return back()->with(
                'error',
                'No valid trainee records found.'
            );
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                OsaertTrainee::insert($chunk);
            }
        });

        return back()->with(
            'success',
            count($rows) . ' trainee records uploaded successfully.'
        );
    }

    /**
     * Public certificate lookup by certificate number (OSAERT-YYYY-NNNNNN).
     * It does not list trainees: names are only shown for a valid number.
     */
    public function verify(Request $request)
    {
        $number = strtoupper(trim((string) $request->query('certificate', '')));

        if ($number === '') {
            return view('osaert.verify', ['number' => '', 'notFound' => false]);
        }

        if (preg_match('/^OSAERT-[0-9-]+-(\d{1,6})$/', $number, $m)) {
            $trainee = OsaertTrainee::find((int) $m[1]);

            if ($trainee && $trainee->isCompleted()
                && in_array($number, [$trainee->certificate_number, $trainee->legacy_certificate_number], true)) {
                return redirect()->route('osaert.verify.certificate', $trainee);
            }
        }

        return view('osaert.verify', ['number' => $number, 'notFound' => true]);
    }

    public function verifyCertificate(OsaertTrainee $trainee)
    {
        // Certificates are only issued on completion.
        abort_unless($trainee->isCompleted(), 404);

        return view(
            'admin.osaert.verify-certificate',
            compact('trainee')
        );
    }
}
