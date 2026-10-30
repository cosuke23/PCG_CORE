<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use App\Models\CourseStudent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrainingCourseStudentController extends Controller
{
    /**
     * Display all students enrolled in a training course.
     */
    public function index(TrainingCourse $course)
    {
        $students = CourseStudent::with('user')
            ->where('course_id', $course->id)
            ->latest()
            ->get();

        return view(
            'admin.training_courses.students.index',
            compact('course', 'students')
        );
    }


    /**
     * Show the form for adding a student.
     */
    public function create(TrainingCourse $course)
    {
        return view(
            'admin.training_courses.students.create',
            compact('course')
        );
    }


    /**
     * Store a single student enrollment.
     */
    public function store(
        Request $request,
        TrainingCourse $course
    ) {
        $request->validate([
            'personnel_id' => [
                'required',
                'string',
            ],
        ]);

        $personnelId = trim($request->personnel_id);

        /*
        |--------------------------------------------------------------------------
        | Find Personnel
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'personnel_id',
            $personnelId
        )->first();

        if (!$user) {

            return back()
                ->withInput()
                ->withErrors([
                    'personnel_id' =>
                        'Personnel ID not found in the system.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Enrollment
        |--------------------------------------------------------------------------
        */

        $alreadyEnrolled = CourseStudent::where(
            'course_id',
            $course->id
        )
            ->where(
                'user_id',
                $user->id
            )
            ->exists();


        if ($alreadyEnrolled) {

            return back()
                ->withInput()
                ->withErrors([
                    'personnel_id' =>
                        'This personnel is already enrolled in this training course.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Create Enrollment
        |--------------------------------------------------------------------------
        */

        CourseStudent::create([

            'course_id' => $course->id,

            'user_id' => $user->id,

        ]);


        return redirect()
            ->route(
                'admin.training-courses.students.index',
                $course->id
            )
            ->with(
                'success',
                'Student successfully added to the training course.'
            );
    }


    /**
     * Remove student from training course.
     */
    public function destroy(
        TrainingCourse $course,
        CourseStudent $student
    ) {

        /*
        |--------------------------------------------------------------------------
        | Security Check
        |--------------------------------------------------------------------------
        */

        if ($student->course_id != $course->id) {

            abort(
                404,
                'Student enrollment does not belong to this training course.'
            );

        }


        $student->delete();


        return redirect()
            ->route(
                'admin.training-courses.students.index',
                $course->id
            )
            ->with(
                'success',
                'Student successfully removed from the training course.'
            );
    }


    /**
     * Download CSV template.
     */
    public function downloadTemplate(
        TrainingCourse $course
    ) {

        $filename =
            'training_course_students_template.csv';


        $headers = [

            'Content-Type' =>
                'text/csv; charset=UTF-8',

            'Content-Disposition' =>
                'attachment; filename="' .
                $filename .
                '"',

        ];


        /*
        |--------------------------------------------------------------------------
        | CSV Columns
        |--------------------------------------------------------------------------
        */

        $columns = [

            'personnel_id',

        ];


        /*
        |--------------------------------------------------------------------------
        | Sample Data
        |--------------------------------------------------------------------------
        */

        $sampleData = [

            [
                'personnel_id' =>
                    'PCG-0001',
            ],

            [
                'personnel_id' =>
                    'PCG-0002',
            ],

        ];


        $callback = function () use (
            $columns,
            $sampleData
        ) {

            $file =
                fopen(
                    'php://output',
                    'w'
                );


            /*
            |--------------------------------------------------------------------------
            | UTF-8 BOM
            |--------------------------------------------------------------------------
            |
            | Allows Excel to properly read UTF-8 characters.
            |
            */

            fprintf(
                $file,
                chr(0xEF) .
                chr(0xBB) .
                chr(0xBF)
            );


            /*
            |--------------------------------------------------------------------------
            | CSV Header
            |--------------------------------------------------------------------------
            */

            fputcsv(
                $file,
                $columns
            );


            /*
            |--------------------------------------------------------------------------
            | Sample Rows
            |--------------------------------------------------------------------------
            */

            foreach (
                $sampleData
                as $row
            ) {

                fputcsv(
                    $file,
                    $row
                );

            }


            fclose($file);

        };


        return response()->stream(

            $callback,

            200,

            $headers

        );
    }


    /**
     * Import students from CSV file.
     */
    public function import(
        Request $request,
        TrainingCourse $course
    ) {

        /*
        |--------------------------------------------------------------------------
        | Validate Uploaded File
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'csv_file' => [

                'required',

                'file',

                'mimes:csv,txt',

                'max:5120',

            ],

        ]);


        $file =
            $request
                ->file('csv_file');


        /*
        |--------------------------------------------------------------------------
        | Open CSV
        |--------------------------------------------------------------------------
        */

        $handle =
            fopen(
                $file->getRealPath(),
                'r'
            );


        if (!$handle) {

            return back()
                ->withErrors([

                    'csv_file' =>
                        'Unable to open CSV file.',

                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Read Header
        |--------------------------------------------------------------------------
        */

        $header =
            fgetcsv(
                $handle
            );


        if (!$header) {

            return back()
                ->withErrors([

                    'csv_file' =>
                        'CSV file is empty.',

                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Remove UTF-8 BOM
        |--------------------------------------------------------------------------
        */

        $header[0] =
            preg_replace(

                '/^\xEF\xBB\xBF/',

                '',

                $header[0]

            );


        /*
        |--------------------------------------------------------------------------
        | Normalize Headers
        |--------------------------------------------------------------------------
        */

        $header =
            array_map(

                function ($column) {

                    return strtolower(

                        trim($column)

                    );

                },

                $header

            );


        /*
        |--------------------------------------------------------------------------
        | Required Column
        |--------------------------------------------------------------------------
        */

        if (

            !in_array(

                'personnel_id',

                $header

            )

        ) {

            fclose($handle);


            return back()
                ->withErrors([

                    'csv_file' =>
                        'Invalid CSV format. Required column: personnel_id',

                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Import Counters
        |--------------------------------------------------------------------------
        */

        $successCount = 0;

        $failedCount = 0;

        $skippedCount = 0;

        $errors = [];


        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Personnel IDs Inside CSV
        |--------------------------------------------------------------------------
        */

        $processedPersonnelIds = [];


        /*
        |--------------------------------------------------------------------------
        | Row Number
        |--------------------------------------------------------------------------
        */

        $rowNumber = 1;


        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Read CSV Rows
            |--------------------------------------------------------------------------
            */

            while (

                (

                    $row =
                        fgetcsv(
                            $handle
                        )

                )

                !== false

            ) {

                $rowNumber++;


                /*
                |--------------------------------------------------------------------------
                | Skip Empty Rows
                |--------------------------------------------------------------------------
                */

                if (

                    count(
                        array_filter($row)
                    ) === 0

                ) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Combine Header and Row
                |--------------------------------------------------------------------------
                */

                if (

                    count($row)
                    !==
                    count($header)

                ) {

                    $failedCount++;

                    $errors[] =

                        "Row {$rowNumber}: Invalid number of columns.";

                    continue;

                }


                $data =

                    array_combine(

                        $header,

                        $row

                    );


                /*
                |--------------------------------------------------------------------------
                | Clean Personnel ID
                |--------------------------------------------------------------------------
                */

                $personnelId =

                    isset(
                        $data['personnel_id']
                    )

                        ? trim(
                            $data['personnel_id']
                        )

                        : null;


                /*
                |--------------------------------------------------------------------------
                | Validate Personnel ID
                |--------------------------------------------------------------------------
                */

                if (

                    empty(
                        $personnelId
                    )

                ) {

                    $failedCount++;

                    $errors[] =

                        "Row {$rowNumber}: Personnel ID is required.";

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Duplicate Inside CSV
                |--------------------------------------------------------------------------
                */

                if (

                    in_array(

                        $personnelId,

                        $processedPersonnelIds

                    )

                ) {

                    $skippedCount++;

                    $errors[] =

                        "Row {$rowNumber}: Duplicate Personnel ID inside CSV ({$personnelId}).";

                    continue;

                }


                $processedPersonnelIds[] =
                    $personnelId;


                /*
                |--------------------------------------------------------------------------
                | Find Personnel
                |--------------------------------------------------------------------------
                */

                $user =

                    User::where(

                        'personnel_id',

                        $personnelId

                    )->first();


                if (!$user) {

                    $failedCount++;

                    $errors[] =

                        "Row {$rowNumber}: Personnel ID '{$personnelId}' was not found.";

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Check Existing Enrollment
                |--------------------------------------------------------------------------
                */

                $alreadyEnrolled =

                    CourseStudent::where(

                        'course_id',

                        $course->id

                    )
                    ->where(

                        'user_id',

                        $user->id

                    )
                    ->exists();


                if (

                    $alreadyEnrolled

                ) {

                    $skippedCount++;

                    $errors[] =

                        "Row {$rowNumber}: {$personnelId} is already enrolled.";

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Insert Student
                |--------------------------------------------------------------------------
                */

                CourseStudent::create([

                    'course_id' =>
                        $course->id,

                    'user_id' =>
                        $user->id,

                ]);


                $successCount++;

            }


            /*
            |--------------------------------------------------------------------------
            | Close CSV
            |--------------------------------------------------------------------------
            */

            fclose($handle);


            DB::commit();


        } catch (\Exception $e) {

            DB::rollBack();


            fclose($handle);


            return back()
                ->withErrors([

                    'csv_file' =>
                        'Import failed: ' .
                        $e->getMessage(),

                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Import Summary
        |--------------------------------------------------------------------------
        */

        $message =

            "Import completed. " .

            "{$successCount} student(s) added. " .

            "{$skippedCount} skipped. " .

            "{$failedCount} failed.";


        /*
        |--------------------------------------------------------------------------
        | Redirect With Summary
        |--------------------------------------------------------------------------
        */

        return redirect()

            ->route(

                'admin.training-courses.students.index',

                $course->id

            )

            ->with(

                'success',

                $message

            )

            ->with(

                'import_errors',

                $errors

            );

    }
}
