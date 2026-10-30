<?php

namespace App\Http\Controllers;

use App\Models\TrainingCourse;
use Illuminate\Http\Request;

class TrainingCourseController extends Controller
{
    /**
     * Display all training courses.
     */
    public function index()
    {
        $trainingCourses = TrainingCourse::withCount('students')
            ->latest()
            ->get();

        return view('admin.training_courses.index', compact('trainingCourses'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.training_courses.create');
    }

    /**
     * Store new training course.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_name' => [
                'required',
                'string',
                'max:255',
                'unique:training_courses,course_name',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'required',
                'in:Active,Inactive',
            ],
        ]);

        TrainingCourse::create($validated);

        return redirect()
            ->route('admin.training-courses.index')
            ->with('success', 'Training course added successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(TrainingCourse $trainingCourse)
    {
        return view(
            'admin.training_courses.edit',
            compact('trainingCourse')
        );
    }

    /**
     * Update training course.
     */
    public function update(
        Request $request,
        TrainingCourse $trainingCourse
    ) {
        $validated = $request->validate([
            'course_name' => [
                'required',
                'string',
                'max:255',
                'unique:training_courses,course_name,' . $trainingCourse->id,
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'required',
                'in:Active,Inactive',
            ],
        ]);

        $trainingCourse->update($validated);

        return redirect()
            ->route('admin.training-courses.index')
            ->with('success', 'Training course updated successfully.');
    }

    /**
     * Delete training course.
     */
    public function destroy(TrainingCourse $trainingCourse)
    {
        $trainingCourse->delete();

        return redirect()
            ->route('admin.training-courses.index')
            ->with('success', 'Training course deleted successfully.');
    }
}
