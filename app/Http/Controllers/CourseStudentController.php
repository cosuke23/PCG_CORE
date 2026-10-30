<?php

namespace App\Http\Controllers;

use App\Models\CourseStudent;
use App\Models\TrainingCourse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class CourseStudentController extends Controller
{
    /**
     * Display students enrolled in a training course.
     */
    public function index(TrainingCourse $course)
    {
        $students = CourseStudent::with(['trainingCourse', 'personnel'])
            ->where('course_id', $course->id)
            ->latest()
            ->get();

        $personnel = DB::table('reinlist_personal_info')
            ->select(
                'id',
                'serial_number',
                'nickname',
                'sex',
                'civil_status',
                'citizenship',
                'contact_number',
                'email',
                'home_address',
                'permanent_address'
            )
            ->orderBy('serial_number')
            ->get();

        return view(
            'admin.training_courses.students.index',
            compact('course', 'students', 'personnel')
        );
    }

    /**
     * Show the form for adding students.
     */
    public function create(TrainingCourse $course)
    {
        $personnel = DB::table('reinlist_personal_info')
            ->join(
                'users',
                'reinlist_personal_info.serial_number',
                '=',
                'users.serial_number'
            )
            ->select(
                'reinlist_personal_info.id',
                'reinlist_personal_info.serial_number',
                'reinlist_personal_info.serial_number',
                'reinlist_personal_info.nickname',
                'reinlist_personal_info.sex',
                'reinlist_personal_info.civil_status',
                'reinlist_personal_info.contact_number',
                'reinlist_personal_info.email as personal_email',
                'users.rank',
                'users.serial_number as user_serial_number',
                'users.name',
                'users.email',
                'users.command',
                'users.mother_unit',
                'users.sub_unit',
                'users.unit',
                'users.unit_assignment',
                'users.status'
            )
            ->orderBy('users.name')
            ->get();

        return view(
            'admin.training_courses.students.create',
            compact('course', 'personnel')
        );
    }

    /**
     * Store a student manually.
     */
    public function store(Request $request, TrainingCourse $course)
    {
        $request->validate([
            'serial_number' => [
                'required',
                'string',
                'exists:users,serial_number',
            ],
        ]);

        $user = User::where(
            'serial_number',
            $request->serial_number
        )->firstOrFail();

        // Prevent duplicate enrollment
        $exists = CourseStudent::where('course_id', $course->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('serial_number', $user->serial_number);
            })
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'This personnel is already enrolled in this training course.');
        }

        // Same fields as the CSV import, so both kinds of enrollment
        // show up the same way in the student list.
        CourseStudent::create([
            'course_id'     => $course->id,
            'user_id'       => $user->id,
            'serial_number' => $user->serial_number,
            'full_name'     => $user->name,
        ]);

        return redirect()
            ->route(
                'training-courses.students.index',
                ['course' => $course->id]
            )
            ->with('success', 'Student successfully added to the training course.');
    }

    /**
     * Delete a student from the training course.
     */
    public function destroy(
        TrainingCourse $course,
        CourseStudent $student
    ) {
        // Make sure the student belongs to this course
        if ($student->course_id != $course->id) {
            abort(404);
        }

        $student->delete();

        return back()->with(
            'success',
            'Student successfully removed from the training course.'
        );
    }

    /**
     * Download CSV template.
     */
    public function downloadTemplate(
        TrainingCourse $course
    ): StreamedResponse {

        $filename = 'training_course_students_template.csv';

        return response()->streamDownload(function () {

            $handle = fopen('php://output', 'w');

            /*
         * CSV Header
         */
            fputcsv($handle, [
                'serial_number',
                'full_name',
            ]);

            /*
         * Sample rows
         */
            fputcsv($handle, [
                'PCG-0001',
                'JUAN DELA CRUZ',
            ]);

            fputcsv($handle, [
                'PCG-0002',
                'PEDRO SANTOS',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
    /**
     * Import students from CSV.
     */
    public function importCsv(
        Request $request,
        TrainingCourse $course
    ) {

        Log::info('========== CSV IMPORT STARTED ==========');

        Log::info('CSV Import - Course', [
            'course_id' => $course->id,
            'course_name' => $course->name ?? null,
        ]);

        /*
     * Validate uploaded file
     */
        $request->validate([
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120',
            ],
        ]);

        Log::info('CSV Import - File validation passed');

        $file = $request->file('csv_file');

        Log::info('CSV Import - File information', [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'real_path' => $file->getRealPath(),
        ]);

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {

            Log::error('CSV Import - Unable to open CSV file');

            return back()->with(
                'error',
                'Unable to open the CSV file.'
            );
        }

        Log::info('CSV Import - File opened successfully');

        /*
     * Read CSV header
     */
        $header = fgetcsv($handle);

        if (!$header) {

            Log::warning('CSV Import - CSV file is empty');

            fclose($handle);

            return back()->with(
                'error',
                'The CSV file is empty.'
            );
        }

        Log::info('CSV Import - Original header', [
            'header' => $header,
        ]);

        /*
     * Remove UTF-8 BOM
     */
        if (isset($header[0])) {

            $header[0] = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $header[0]
            );
        }

        Log::info('CSV Import - Header after BOM removal', [
            'header' => $header,
        ]);

        /*
     * Normalize headers
     */
        $header = array_map(function ($value) {
            return strtolower(trim($value));
        }, $header);

        Log::info('CSV Import - Normalized header', [
            'header' => $header,
        ]);

        /*
     * Check required columns
     */
        $requiredColumns = [
            'serial_number',
            'full_name',
        ];

        foreach ($requiredColumns as $column) {

            if (!in_array($column, $header)) {

                Log::warning('CSV Import - Missing required column', [
                    'missing_column' => $column,
                    'header' => $header,
                ]);

                fclose($handle);

                return back()->with(
                    'error',
                    "Invalid CSV format. The CSV must contain a {$column} column."
                );
            }
        }

        Log::info('CSV Import - Required columns found');

        /*
     * Get column indexes
     */
        $personnelIdIndex = array_search(
            'serial_number',
            $header
        );

        $fullNameIndex = array_search(
            'full_name',
            $header
        );

        Log::info('CSV Import - Column indexes', [
            'serial_number_index' => $personnelIdIndex,
            'full_name_index' => $fullNameIndex,
        ]);

        $successCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $errors = [];

        $rowNumber = 1;

        Log::info('CSV Import - Starting database transaction');

        DB::beginTransaction();

        try {

            while (($row = fgetcsv($handle)) !== false) {

                $rowNumber++;

                Log::info('CSV Import - Processing row', [
                    'row_number' => $rowNumber,
                    'row_data' => $row,
                ]);

                /*
             * Skip completely empty rows
             */
                if (
                    count($row) === 1 &&
                    trim($row[0] ?? '') === ''
                ) {

                    Log::info('CSV Import - Empty row skipped', [
                        'row_number' => $rowNumber,
                    ]);

                    continue;
                }

                /*
             * Get CSV values
             */
                $personnelId = trim(
                    $row[$personnelIdIndex] ?? ''
                );

                $fullName = trim(
                    $row[$fullNameIndex] ?? ''
                );

                Log::info('CSV Import - Parsed row values', [
                    'row_number' => $rowNumber,
                    'serial_number' => $personnelId,
                    'full_name' => $fullName,
                ]);

                /*
             * Missing Personnel ID
             */
                if ($personnelId === '') {

                    $failedCount++;

                    $errors[] =
                        "Row {$rowNumber}: Personnel ID is required.";

                    Log::warning('CSV Import - Personnel ID missing', [
                        'row_number' => $rowNumber,
                    ]);

                    continue;
                }

                /*
             * Missing Full Name
             */
                if ($fullName === '') {

                    $failedCount++;

                    $errors[] =
                        "Row {$rowNumber}: Full Name is required.";

                    Log::warning('CSV Import - Full Name missing', [
                        'row_number' => $rowNumber,
                        'serial_number' => $personnelId,
                    ]);

                    continue;
                }

                /*
             * Find personnel using Personnel ID
             */
                Log::info('CSV Import - Searching personnel', [
                    'serial_number' => $personnelId,
                ]);

                $user = User::where(
                    'serial_number',
                    $personnelId
                )->first();

                if (!$user) {

                    $failedCount++;

                    $errors[] =
                        "Row {$rowNumber}: Personnel ID '{$personnelId}' was not found.";

                    Log::warning('CSV Import - Personnel not found', [
                        'row_number' => $rowNumber,
                        'serial_number' => $personnelId,
                    ]);

                    continue;
                }

                Log::info('CSV Import - Personnel found', [
                    'row_number' => $rowNumber,
                    'user_id' => $user->id,
                    'serial_number' => $user->serial_number,
                    'database_name' => $user->name ?? null,
                ]);

                /*
             * Normalize names before comparison
             */
                $databaseFullName = strtolower(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        trim($user->name ?? '')
                    )
                );

                $csvFullName = strtolower(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        trim($fullName)
                    )
                );

                Log::info('CSV Import - Comparing names', [
                    'row_number' => $rowNumber,
                    'serial_number' => $personnelId,
                    'csv_full_name' => $csvFullName,
                    'database_full_name' => $databaseFullName,
                ]);

                /*
             * Check if Full Name matches
             */
                if ($databaseFullName !== $csvFullName) {

                    $failedCount++;

                    $errors[] =
                        "Row {$rowNumber}: Full Name '{$fullName}' does not match the registered name for Personnel ID '{$personnelId}'.";

                    Log::warning('CSV Import - Full name mismatch', [
                        'row_number' => $rowNumber,
                        'serial_number' => $personnelId,
                        'csv_name' => $fullName,
                        'database_name' => $user->name ?? null,
                    ]);

                    continue;
                }

                Log::info('CSV Import - Full name matched', [
                    'row_number' => $rowNumber,
                    'serial_number' => $personnelId,
                ]);

                /*
             * Check duplicate enrollment
             */
                Log::info('CSV Import - Checking duplicate enrollment', [
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                ]);

                $alreadyEnrolled = CourseStudent::where(
                    'course_id',
                    $course->id
                )
                    ->where(
                        'serial_number',
                        $user->serial_number
                    )
                    ->exists();

                if ($alreadyEnrolled) {

                    $skippedCount++;

                    $errors[] =
                        "Row {$rowNumber}: {$personnelId} ({$user->name}) is already enrolled.";

                    Log::info('CSV Import - Student already enrolled', [
                        'row_number' => $rowNumber,
                        'course_id' => $course->id,
                        'user_id' => $user->id,
                        'serial_number' => $personnelId,
                    ]);

                    continue;
                }

                /*
             * Create enrollment
             */
                Log::info('CSV Import - Creating enrollment', [
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'serial_number' => $personnelId,
                    'full_name' => $user->name ?? null,
                ]);

                CourseStudent::create([
                    'course_id' => $course->id,
                    'serial_number' => $personnelId,
                    'full_name' => $fullName,
                    'user_id'   => $user->id,
                ]);

                $successCount++;

                Log::info('CSV Import - Enrollment created successfully', [
                    'row_number' => $rowNumber,
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'serial_number' => $personnelId,
                ]);
            }

            fclose($handle);

            Log::info('CSV Import - CSV processing completed', [
                'success_count' => $successCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'total_errors' => count($errors),
            ]);

            DB::commit();

            Log::info('CSV Import - Database transaction committed');
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('CSV Import - Transaction failed', [
                'course_id' => $course->id,
                'row_number' => $rowNumber,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (is_resource($handle)) {
                fclose($handle);
            }

            return back()->with(
                'error',
                'CSV import failed: ' . $e->getMessage()
            );
        }

        /*
     * Build import summary
     */
        $message =
            "CSV import completed. " .
            "{$successCount} student(s) added, " .
            "{$skippedCount} skipped, " .
            "{$failedCount} failed.";

        Log::info('CSV Import - Final summary', [
            'course_id' => $course->id,
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
        ]);

        Log::info('========== CSV IMPORT FINISHED ==========');

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }
}
