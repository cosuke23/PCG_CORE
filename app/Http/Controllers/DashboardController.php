<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile; // ✅ make sure this model exists
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the dashboard page.
     */
    public function index()
    {
        $user = Auth::user();

        $serial = $user->serial_number;

        $rejectedDocs = DB::table('rejected_documents')
            ->where('serial_number', $serial)
            ->where('status', 'rejected')
            ->get();

        // Enrolled MEPTDI classes (was only used as an always-true flag).
        $classes = DB::table('course_students as cs')
            ->leftJoin('training_courses as tc', 'tc.id', '=', 'cs.course_id')
            ->where('cs.serial_number', $serial)
            ->orderByDesc('cs.date_attended')
            ->get(['tc.course_name', 'tc.status as course_status', 'cs.date_attended', 'cs.date_ended']);

        // Required-documents progress (personnel_data has one column per document).
        $documentFields = ['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr',
            'medical_clearance', 'pft_clearance', 'whole_body_picture', 'photo_2x2', 'residential_sketch'];
        $documents = DB::table('personnel_data')->where('personnel_id', $user->id)->first();
        $uploaded = $documents ? collect($documentFields)->filter(fn ($f) => !empty($documents->$f))->count() : 0;

        return view('applicant.dashboard', [
            'user' => $user,
            'rejectedDocs' => $rejectedDocs,
            'rejectedCount' => $rejectedDocs->count(),
            'classes' => $classes,
            'documentStatus' => $documents->status ?? null,
            'documentsUploaded' => $uploaded,
            'documentsTotal' => count($documentFields),
        ]);
    }

    public function profile()
    {
        $user = Auth::user();

        $profile = Profile::where('user_id', $user->id)->first();

        $serialNumber = $user->serial_number;
        $personalEmail = $user->email;

        $courses = DB::table('courses')->get();

        // ================= BASIC INFO =================
        $reinlistData = DB::table('reinlist_basic_info as rbi')
            ->leftJoin('reinlist_personal_info as rpi', 'rpi.serial_number', '=', 'rbi.serial_number')
            ->select(
                'rbi.serial_number as rbi_serial_number',
                'rbi.last_name',
                'rbi.first_name',
                'rbi.middle_name',
                'rbi.extension_name',
                'rbi.rank',
                'rbi.class as m_class',
                'rbi.birthday',
                'rbi.birth_place',

                'rpi.nickname',
                'rpi.sex',
                'rpi.civil_status',
                'rpi.citizenship',
                'rpi.religion',
                'rpi.blood_type',
                'rpi.height',
                'rpi.weight',
                'rpi.body_build',
                'rpi.hair_color',
                'rpi.skin_color',
                'rpi.eye_color',
                'rpi.head_size',
                'rpi.foot_size',
                'rpi.waist_size',
                'rpi.distinct_features',
                'rpi.contact_number',
                'rpi.email',
                'rpi.home_address',
                'rpi.permanent_address',
                'rpi.tin',
                'rpi.philhealth',
                'rpi.pagibig',
                'rpi.gsis',
                'rpi.sss',
                'rpi.drivers_license'
            )
            ->where('rbi.serial_number', $serialNumber)
            ->first();

        // ✅ FIX: HANDLE NEW USERS (NULL DATA)
        if (!$reinlistData) {
            $reinlistData = (object) [
                'rbi_serial_number' => $serialNumber,
                'last_name' => '',
                'first_name' => '',
                'middle_name' => '',
                'extension_name' => '',
                'rank' => $user->rank ?? '',
                'm_class' => '',
                'birthday' => '',
                'birth_place' => '',

                'nickname' => '',
                'sex' => '',
                'civil_status' => '',
                'citizenship' => '',
                'religion' => '',
                'blood_type' => '',
                'height' => '',
                'weight' => '',
                'body_build' => '',
                'hair_color' => '',
                'skin_color' => '',
                'eye_color' => '',
                'contact_number' => '',
                'email' => $user->email ?? '',
                'home_address' => '',
                'permanent_address' => '',
                'tin' => '',
                'philhealth' => '',
                'pagibig' => '',
                'gsis' => '',
            ];
        }

        // ================= ACADEMIC =================
        $academic = DB::table('reinlist_academic')
            ->where('serial_number', $serialNumber)
            ->get();

        $reinlistData->academic_tertiary = $academic->where('level', 'tertiary')->values();
        $reinlistData->academic_masteral = $academic->where('level', 'masteral')->values();

        $reinlistData->academic_secondary = $academic->where('level', 'secondary')->first() ?? (object)[];
        $reinlistData->academic_primary = $academic->where('level', 'primary')->first() ?? (object)[];

        $reinlistData->vocational = $academic->where('level', 'vocational')->values()->map(function ($v) {
            return [
                'course' => $v->course ?? '',
                'authority' => $v->remarks ?? '',
                'school' => $v->school ?? '',
                'period' => ($v->year_start ?? '') . ' - ' . ($v->year_end ?? ''),
            ];
        });

        // ================= TRAININGS =================
        $trainingRaw = DB::table('reinlist_trainings')
            ->where('serial_number', $serialNumber)
            ->get()
            ->groupBy('category');

        $reinlistData->trainings = [
            'civilian_others_local'   => $trainingRaw['others_local'] ?? collect(),
            'civilian_others_foreign' => $trainingRaw['others_foreign'] ?? collect(),
            'coastguard'     => $trainingRaw['coastguard'] ?? collect(),
            'others_local'   => $trainingRaw['others_local'] ?? collect(),
            'others_foreign' => $trainingRaw['others_foreign'] ?? collect(),
            'military'       => $trainingRaw['military'] ?? collect(),
            'military_others_local' => $trainingRaw['military_others_local'] ?? collect(),
            'military_others_foreign' => $trainingRaw['military_others_foreign'] ?? collect(),
        ];

        // ================= DEPENDENTS =================
        $dependents = DB::table('reinlist_dependents')
            ->where('serial_number', $serialNumber)
            ->get()
            ->map(function ($d) {
                return [
                    'name' => $d->name,
                    'relationship' => $d->relationship,
                    'bday' => $d->birthdate,
                ];
            })
            ->values();

        $reinlistData->dependents = $dependents->isEmpty()
            ? collect([['name' => '', 'relationship' => '', 'bday' => '']])
            : $dependents;
        $appointments = DB::table('reinlist_appointments')
            ->where('serial_number', $serialNumber)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($a) => [
                'description' => $a->description,
                'date' => $a->date,
                'authority' => $a->authority,
            ])
            ->values()
            ->toArray();

        $reinlistData->appointments = count($appointments)
            ? $appointments
            : [
                ['description' => '', 'date' => '', 'authority' => '']
            ];
        // ================= APPOINTMENTS =================
        $dutiesRaw = DB::table('reinlist_duties')
            ->where('serial_number', $serialNumber)
            ->get()
            ->groupBy('type');

        $reinlistData->duties = [];

        $types = [
            'shipboard',
            'shipboard_collateral',
            'shorebased',
            'shorebased_collateral',
            'special',
            'govt',
            'non_govt'
        ];

        foreach ($types as $type) {

            $items = isset($dutiesRaw[$type]) ? $dutiesRaw[$type] : collect();

            $mapped = $items->map(function ($d) use ($type) {

                if (in_array($type, ['govt', 'non_govt'])) {
                    return [
                        'position' => $d->position,
                        'agency' => $d->agency,
                        'period' => $d->period,
                    ];
                }

                return [
                    'unit' => $d->unit,
                    'position' => $d->position,
                    'period' => $d->period,
                    'authority' => $d->authority,
                ];
            })->values()->toArray();

            // ✅ FORCE 1 ROW IF EMPTY
            if (empty($mapped)) {
                $mapped = in_array($type, ['govt', 'non_govt'])
                    ? [['position' => '', 'agency' => '', 'period' => '']]
                    : [['unit' => '', 'position' => '', 'period' => '', 'authority' => '']];
            }

            $reinlistData->duties[$type] = $mapped;
        }
        $militaryAwards = DB::table('reinlist_awards')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($a) => [
                'branch' => $a->authority ?? '',   // IMPORTANT FIX
                'category' => $a->category ?? '',
                'type' => $a->type ?? '',
                'authority' => $a->authority ?? '',
            ])
            ->toArray();

        $reinlistData->militaryAwards = !empty($militaryAwards)
            ? $militaryAwards
            : [[
                'branch' => '',
                'category' => '',
                'type' => '',
                'authority' => ''
            ]];


        // ================= CAREERS =================
        $careers = DB::table('reinlist_careers')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($c) => [
                'student' => $c->student,
                'sea' => $c->sea,
                'staff' => $c->staff,
                'instructor' => $c->instructor,
                'command' => $c->command,
                'organization_affiliation' => $c->organization_affiliation,
                'civil_service_eligibility' => $c->civil_service_eligibility,
                'compulsory_retirement' => $c->compulsory_retirement,
            ]);

        $reinlistData->careers = $careers->isEmpty()
            ? collect([[
                'student' => '',
                'sea' => '',
                'staff' => '',
                'instructor' => '',
                'command' => '',
                'organization_affiliation' => '',
                'civil_service_eligibility' => '',
                'compulsory_retirement' => ''
            ]])
            : $careers;


        // ================= CG AWARDS / DECORATIONS =================
        $cgAwards = DB::table('reinlist_cg_awards_decorations')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($a) => [
                'type' => $a->type,
                'authority' => $a->authority,
            ]);

        $reinlistData->cgAwardsDecorations = $cgAwards->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $cgAwards;


        // ================= CG LETTERS =================
        $cgLetters = DB::table('reinlist_cg_letters')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'type' => $l->type,
                'authority' => $l->authority,
            ]);

        $reinlistData->cgLetters = $cgLetters->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $cgLetters;

        // ================= OTHER LETTERS =================
        $otherLetters = DB::table('reinlist_other_letters')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'type' => $l->type,
                'authority' => $l->authority,
            ]);

        $reinlistData->otherLetters = $otherLetters->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $otherLetters;

        $otherAppreciations = DB::table('reinlist_other_appreciations')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'type' => $l->type,
                'authority' => $l->authority,
            ]);

        $reinlistData->otherAppreciations = $otherAppreciations->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $otherAppreciations;

        $otherPlaques = DB::table('reinlist_other_plaques')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'type' => $l->type,
                'authority' => $l->authority,
            ]);

        $reinlistData->otherPlaques = $otherPlaques->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $otherPlaques;


        // ================= CG CERTIFICATES =================
        $cgCertificates = DB::table('reinlist_cg_certificates')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($c) => [
                'type' => $c->type,
            ]);

        $reinlistData->cgCertificates = $cgCertificates->isEmpty()
            ? collect([['type' => '']])
            : $cgCertificates;


        $cgAppreciations = DB::table('reinlist_cg_appreciations')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($a) => [
                'type' => $a->type,
                'authority' => $a->authority,
            ]);

        $reinlistData->cgAppreciations = $cgAppreciations->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $cgAppreciations;
        $cgPlaques = DB::table('reinlist_cg_plaques')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($p) => [
                'type' => $p->type,
                'authority' => $p->authority,
            ]);

        $reinlistData->cgPlaques = $cgPlaques->isEmpty()
            ? collect([['type' => '', 'authority' => '']])
            : $cgPlaques;
        $militaryLetters = DB::table('reinlist_military_letters')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($l) {
                return [
                    'type' => $l->type ?? '',
                    'authority' => $l->authority ?? '',
                ];
            })
            ->values();

        $reinlistData->militaryLetters = $militaryLetters->isEmpty()
            ? collect([
                [
                    'type' => '',
                    'authority' => '',
                ]
            ])
            : $militaryLetters;
        $otherLetterCommendations = DB::table('reinlist_civilian_awards')
            ->where('serial_number', $serialNumber)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'type' => $c->type ?? '',
                    'authority' => $c->authority ?? '',
                ];
            });

        $reinlistData->otherLetterCommendations = $otherLetterCommendations->isEmpty()
            ? collect([[
                'type' => '',
                'authority' => ''
            ]])
            : $otherLetterCommendations;
        // ================= RETURN =================
        if ($profile) {
            return view('applicant.updated_profile', compact(
                'user',
                'profile',
                'reinlistData',
                'courses'
            ));
        }

        return view('applicant.profile', compact(
            'user',
            'profile',
            'reinlistData',
            'courses',
            'serialNumber',
            'personalEmail'
        ));
    }

    public function uploadDocuments()
    {
        $user = Auth::user();

        $serial = $user->serial_number;

        // ✅ Get existing record (ONLY ONE)
        $documents = DB::table('personnel_data')
            ->where('serial_number', $serial)
            ->first();

        $personal = DB::table('reinlist_basic_info')
            ->where('serial_number', $serial)
            ->first();

        return view('applicant.upload-documents', [
            'user' => $user,
            'documents' => $documents,
            'personal' => $personal
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'district' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'extension_name' => 'nullable|string|max:10',
            'birthday' => 'nullable|date',
            'sex' => 'nullable|string|max:10',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            // Permanent Address
            'perm_street' => 'nullable|string|max:255',
            'perm_city' => 'nullable|string|max:255',
            'perm_province' => 'nullable|string|max:255',
            'perm_zipcode' => 'nullable|string|max:10',
            'perm_years_residency' => 'nullable|numeric',

            // Present Address
            'pres_street' => 'nullable|string|max:255',
            'pres_city' => 'nullable|string|max:255',
            'pres_province' => 'nullable|string|max:255',
            'pres_zipcode' => 'nullable|string|max:10',
            'pres_years_residency' => 'nullable|numeric',

            // Education
            'education_level' => 'nullable|string|max:255',
            'tertiary_course' => 'nullable|string|max:255',
            'tertiary_school' => 'nullable|string|max:255',
            'tertiary_year_graduated' => 'nullable|string|max:4',
            'secondary_school' => 'nullable|string|max:255',
            'secondary_strand' => 'nullable|string|max:255',
            'secondary_year_graduated' => 'nullable|string|max:4',
            'elementary_school' => 'nullable|string|max:255',
            'elementary_year_graduated' => 'nullable|string|max:4',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('profile_photos', 'public');
        } else {
            $photoPath = optional($user->profile)->photo;
        }
        // Update or create profile
        $year = now()->year; // 2025
        $district = $validated['district']; // HRMU-SV
        $random = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);

        $applicant_id = "{$year}-{$district}-{$random}";
        $profile = Profile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'district' => $district,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'extension_name' => $validated['extension_name'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'sex' => $validated['sex'] ?? null,
                'photo' => $photoPath,

                // Permanent Address
                'perm_street' => $validated['perm_street'] ?? null,
                'perm_city' => $validated['perm_city'] ?? null,
                'perm_province' => $validated['perm_province'] ?? null,
                'perm_zipcode' => $validated['perm_zipcode'] ?? null,
                'perm_years_residency' => $validated['perm_years_residency'] ?? null,

                // Present Address
                'pres_street' => $validated['pres_street'] ?? null,
                'pres_city' => $validated['pres_city'] ?? null,
                'pres_province' => $validated['pres_province'] ?? null,
                'pres_zipcode' => $validated['pres_zipcode'] ?? null,
                'pres_years_residency' => $validated['pres_years_residency'] ?? null,

                // Education
                'education_level' => $validated['education_level'] ?? null,
                'tertiary_course' => $validated['tertiary_course'] ?? null,
                'tertiary_school' => $validated['tertiary_school'] ?? null,
                'tertiary_year_graduated' => $validated['tertiary_year_graduated'] ?? null,
                'secondary_school' => $validated['secondary_school'] ?? null,
                'secondary_strand' => $validated['secondary_strand'] ?? null,
                'secondary_year_graduated' => $validated['secondary_year_graduated'] ?? null,
                'elementary_school' => $validated['elementary_school'] ?? null,
                'elementary_year_graduated' => $validated['elementary_year_graduated'] ?? null,
                'profile_code' => $applicant_id ?? null,
            ]
        );

        // Log info
        Log::info('Profile updated', [
            'user_id' => $user->id,
            'name' => $user->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Redirect with SweetAlert success
        return redirect()->route('profile')->with('success', 'Profile updated successfully!');
    }
}
