<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PageController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TempRegistrationController;
use App\Http\Controllers\ApplicantDocumentController;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminLoginController;

use App\Http\Controllers\ReenlistController;
use App\Http\Controllers\UnitDocumentController;
use App\Http\Controllers\UnitUserController;

use App\Http\Controllers\HRAnalyticsController;

use App\Http\Controllers\AdministrativeTrackController;
use App\Http\Controllers\PersonnelAdministrativeTrainingController;

use App\Http\Controllers\OperationTrackController;
use App\Http\Controllers\PersonnelOperationalTrainingController;

use App\Http\Controllers\TrainingCourseController;
use App\Http\Controllers\CourseStudentController;

use App\Http\Controllers\ApplicantTrainingController;
use App\Http\Controllers\TrainingApplicationController;

use App\Http\Controllers\OsaertController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| MEPCOM HRMIS Web Routes
|
| Four audiences, each with its own access check:
|   - public          : no login
|   - applicant       : Laravel "auth" guard (users table)
|   - unit checker    : "unit.session" (unit_users table, session flag)
|   - admin           : "admin.session" (credentials from .env, session flag)
|
| Removed in the security hardening (they pointed at missing controller
| methods and always returned HTTP 500):
|   /services, /decision/submit, /meptdi-lms, GET /applicant/training/apply
| Face verification (/face-verify...) removed 2026-10-30: never linked,
| never finished; restore from git history (commit 88f19be) if needed.
|
*/


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [\App\Http\Controllers\ContactController::class, 'show'])->name('contact');
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

Route::get('/institutes/meptdi', function () {
    return view('pages.meptdi');
})->name('meptdi');

// Public OSAERT certificate verification
// Public OSAERT certificate verification (QR codes on printed certificates
// link to /osaert/verify/{id}). Throttled to slow down enumeration.
Route::middleware('throttle:public-verify')->group(function () {
    Route::get('/osaert/verify', [OsaertController::class, 'verify'])
        ->name('osaert.verify');
    Route::get('/osaert/verify/{trainee}', [OsaertController::class, 'verifyCertificate'])
        ->name('osaert.verify.certificate');
});


// Uploaded personnel files. FileController checks the viewer: admins and
// unit checkers see all files, personnel only their own.
Route::get('/files/{path}', [\App\Http\Controllers\FileController::class, 'show'])
    ->where('path', '.*')
    ->name('files.show');


/*
|--------------------------------------------------------------------------
| Applicant registration & login
|--------------------------------------------------------------------------
*/

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
    ->name('register');
Route::post('/register.submit', [RegisterController::class, 'register'])
    ->middleware('throttle:register')
    ->name('register.submit');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


/*
|--------------------------------------------------------------------------
| APPLICANT (logged-in personnel)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');
    Route::get('/upload-documents', [DashboardController::class, 'uploadDocuments'])
        ->name('upload.documents');
    Route::post('/profile/update', [DashboardController::class, 'updateProfile'])
        ->name('profile.update');

    Route::post('/documents/upload', [ApplicantDocumentController::class, 'store'])
        ->name('documents.store');
    Route::get('/documents/upload', [ApplicantDocumentController::class, 'create']);

    Route::get('/upload-201', [ApplicantDocumentController::class, 'index_201'])
        ->name('upload.201');
    Route::post('/upload-201', [ApplicantDocumentController::class, 'store_201'])
        ->name('upload.201.store');


    // Multi-step profile (SOI) form
    Route::post('/temp-save/step1', [TempRegistrationController::class, 'saveStep1'])->name('temp.save.step1');
    Route::post('/temp-save/step2', [TempRegistrationController::class, 'saveStep2'])->name('temp.save.step2');
    Route::post('/temp-save/step3', [TempRegistrationController::class, 'saveStep3'])->name('temp.save.step3');
    Route::post('/temp-save/step4', [TempRegistrationController::class, 'saveStep4'])->name('temp.save.step4');
    Route::post('/temp-save/step5', [TempRegistrationController::class, 'saveStep5'])->name('temp.save.step5');
    Route::post('/temp-save/step6', [TempRegistrationController::class, 'saveStep6'])->name('temp.save.step6');

    Route::post('/reinlist/store', [ReenlistController::class, 'store']);

    Route::get('/generate-soi', [ProfileController::class, 'generateSOI'])
        ->name('generate.soi');

    Route::get('/applicant/administrative-training', [ApplicantTrainingController::class, 'administrative'])
        ->name('applicant.administrative.training');
    Route::get('/applicant/operational-training', [ApplicantTrainingController::class, 'operational'])
        ->name('applicant.operational.training');
    // GET /applicant/training/apply removed: unlinked, and its view never existed.
    Route::post('/applicant/training/apply', [ApplicantTrainingController::class, 'store'])
        ->name('applicant.training.store');
});


/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [AdminLoginController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.submit');
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
// Sidebar links use GET
Route::get('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout.legacy');

// Two-factor sign-in (after the password, before the admin session exists)
Route::prefix('admin/two-factor')->name('admin.two-factor.')->group(function () {
    Route::get('/setup', [AdminLoginController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AdminLoginController::class, 'confirmSetup'])->middleware('throttle:two-factor')->name('setup.confirm');
    Route::get('/challenge', [AdminLoginController::class, 'showChallenge'])->name('challenge');
    Route::post('/challenge', [AdminLoginController::class, 'verifyChallenge'])->middleware('throttle:two-factor')->name('challenge.verify');
    Route::get('/recovery-codes', [AdminLoginController::class, 'showRecoveryCodes'])->name('recovery-codes');
});

/*
| Roles (admin_users.role): super_admin = everything; hr_admin = registrations,
| HR analytics, unit accounts, training, messages; lms_admin = training
| tracks, applications, courses, students, OSAERT. Every state-changing
| request is written to admin_audit_logs (admin.audit).
*/
Route::middleware(['admin.session', 'admin.audit'])->group(function () {

    // ---- super admin + HR admin, organisation-wide pages ----
    Route::middleware(['admin.role:super_admin,hr_admin', 'admin.orgwide'])->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/admin/approve/{id}', [AdminController::class, 'approve']);
        Route::post('/admin/reject/{id}', [AdminController::class, 'reject']);

        Route::get('/admin/hr-analytics', [HRAnalyticsController::class, 'index'])
            ->name('admin.hr-analytics');

        // Unit checker accounts
        Route::post('/unit-users/store', [UnitUserController::class, 'store'])
            ->name('unit-users.store');
        Route::get('/admin/unit-users', [\App\Http\Controllers\UnitAccountController::class, 'index'])
            ->name('admin.unit-users.index');
        Route::post('/admin/unit-users/{id}/password', [\App\Http\Controllers\UnitAccountController::class, 'updatePassword'])
            ->whereNumber('id')->name('admin.unit-users.password');
        Route::post('/admin/unit-users/{id}/role', [\App\Http\Controllers\UnitAccountController::class, 'updateRole'])
            ->whereNumber('id')->name('admin.unit-users.role');
        Route::post('/admin/unit-users/{id}/status', [\App\Http\Controllers\UnitAccountController::class, 'updateStatus'])
            ->whereNumber('id')->name('admin.unit-users.status');

        // Contact messages
        Route::get('/admin/messages', [\App\Http\Controllers\ContactController::class, 'index'])->name('admin.messages.index');
        Route::post('/admin/messages/{id}/status', [\App\Http\Controllers\ContactController::class, 'updateStatus'])
            ->whereNumber('id')->name('admin.messages.status');
    });

    // ---- super admin + HR admin, organisation-scoped modules (App\Services\OrgScope) ----
    Route::middleware('admin.role:super_admin,hr_admin')->group(function () {
        // Organizational structure (view; changes are super admin only, below)
        Route::get('/admin/organization', [\App\Http\Controllers\OrganizationController::class, 'index'])
            ->name('admin.organization.index');

        // Personnel master and assignments
        Route::prefix('admin/personnel')->name('admin.personnel.')
            ->controller(\App\Http\Controllers\PersonnelController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{personnel}', 'show')->whereNumber('personnel')->name('show');
                Route::post('/{personnel}/assignments', 'assign')->whereNumber('personnel')->name('assign');
            });
    });

    // ---- super admin only: organizational structure changes ----
    Route::middleware('admin.role:super_admin')->prefix('admin/organization')->name('admin.organization.')
        ->controller(\App\Http\Controllers\OrganizationController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::post('/{unit}', 'update')->whereNumber('unit')->name('update');
            Route::post('/{unit}/status', 'status')->whereNumber('unit')->name('status');
        });

    // ---- all admin roles: training tracks and applications (organisation-wide) ----
    Route::middleware(['admin.role:super_admin,hr_admin,lms_admin', 'admin.orgwide'])->group(function () {
        Route::get('/administrative-tracks', [AdministrativeTrackController::class, 'index'])
            ->name('administrative.tracks');
        Route::get('/personnel-administrative-training', [PersonnelAdministrativeTrainingController::class, 'index'])
            ->name('personnel.administrative.training');

        Route::prefix('operation-tracks')
            ->name('operation_tracks.')
            ->group(function () {
                Route::get('/', [OperationTrackController::class, 'index'])->name('index');
                Route::post('/', [OperationTrackController::class, 'store'])->name('store');
                Route::put('/{operationTrack}', [OperationTrackController::class, 'update'])->name('update');
                Route::delete('/{operationTrack}', [OperationTrackController::class, 'destroy'])->name('destroy');
            });

        Route::get('/personnel/operational-training', [PersonnelOperationalTrainingController::class, 'index'])
            ->name('personnel.operational.training');

        Route::prefix('admin/training-applications')
            ->name('admin.training-applications.')
            ->group(function () {
                Route::get('/', [TrainingApplicationController::class, 'index'])->name('index');
                Route::patch('/{application}/status', [TrainingApplicationController::class, 'updateStatus'])
                    ->name('status');
            });
    });

    // ---- super admin + LMS admin: MEPTDI LMS and OSAERT ----
    Route::middleware('admin.role:super_admin,lms_admin')->group(function () {
        Route::prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::resource('training-courses', TrainingCourseController::class)->except(['show']);
            });

        Route::prefix('training-courses/{course}/students')
            ->name('training-courses.students.')
            ->group(function () {
                Route::get('/', [CourseStudentController::class, 'index'])->name('index');
                Route::get('/create', [CourseStudentController::class, 'create'])->name('create');
                Route::post('/', [CourseStudentController::class, 'store'])->name('store');
                Route::get('/csv-template', [CourseStudentController::class, 'downloadTemplate'])->name('csv-template');
                Route::delete('/{student}', [CourseStudentController::class, 'destroy'])->name('destroy');
            });

        Route::post('/training-courses/{course}/students/import-csv', [CourseStudentController::class, 'importCsv'])
            ->name('import-csv');

        Route::prefix('admin/osaert')
            ->name('admin.osaert.')
            ->controller(OsaertController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/template', 'template')->name('template');
                Route::post('/bulk-upload', 'bulkUpload')->name('bulk-upload');
                Route::get('/certificates', 'certificates')->name('certificates');
            });
    });

    // ---- super admin only: administrator accounts and audit log ----
    Route::middleware('admin.role:super_admin')->prefix('admin/accounts')->name('admin.accounts.')
        ->controller(\App\Http\Controllers\AdminAccountController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/{admin}/role', 'updateRole')->name('role');
            Route::post('/{admin}/status', 'updateStatus')->name('status');
            Route::post('/{admin}/password', 'resetPassword')->name('password');
            Route::post('/{admin}/two-factor/reset', 'resetTwoFactor')->name('two-factor.reset');
            Route::get('/audit-log', 'auditLog')->name('audit');
        });
});


/*
|--------------------------------------------------------------------------
| UNIT CHECKERS
|--------------------------------------------------------------------------
*/

Route::get('/unit/login', [UnitUserController::class, 'loginForm']);
Route::post('/unit/login', [UnitUserController::class, 'login'])->middleware('throttle:unit-login');
Route::get('/unit/loginM1', [UnitUserController::class, 'loginFormM1']);
Route::post('/unit/loginM1', [UnitUserController::class, 'loginM1'])->middleware('throttle:unit-login');
Route::get('/unit/logout', [UnitUserController::class, 'logout']);

Route::middleware(['unit.session'])->group(function () {

    Route::get('/unit/dashboard', [UnitUserController::class, 'dashboard'])->name('unit.dashboard');

    // Document review (approve / reject per document). The forms post to
    // /unit/documents/review, which used to point at an empty controller.
    // Unit checkers may only review their own unit (checked in the controller).
    Route::post('/unit/documents/review', [UnitDocumentController::class, 'review'])
        ->name('unit.documents.review');
    Route::post('/unit/documents/review-final', [UnitDocumentController::class, 'review'])
        ->name('unit.documents.review.final');

    // M1 checkers only (all units)
    Route::middleware(['unit.m1'])->group(function () {
        Route::get('/unit/dashboardM1', [UnitUserController::class, 'dashboardM1'])->name('unit.dashboard.m1');

        Route::get('/unit/search-serial/{serial}', [UnitUserController::class, 'searchSerial']);

        Route::post('/unit/disciplinary/store', [UnitUserController::class, 'storeDisciplinary'])
            ->name('disciplinary.store');
        Route::delete('/unit/disciplinary/delete/{id}', [UnitUserController::class, 'deleteDisciplinary'])
            ->name('disciplinary.delete');

        Route::prefix('unit')->group(function () {
            Route::get('/201-files', [UnitUserController::class, 'index_201'])->name('unit.201files');
            Route::get('/201-files/{id}', [UnitUserController::class, 'show_201'])->name('unit.201files.show');
        });
    });
});
