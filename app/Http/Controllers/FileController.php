<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Serves uploaded personnel files after an access check.
 *
 * Access: admins and M1 checkers see all files; own-unit checkers see the
 * required documents of their unit's personnel; personnel see their own.
 *
 * Files used to be linked directly (public/documents_toupload/..., etc.), so
 * anyone with the URL could download clearances and 201 files. Links now
 * point to /files/{path}; direct access is blocked in public/.htaccess.
 *
 * {path} is the value stored in the database, prefixed with its area:
 *   documents_toupload/<personnel_data column value>
 *   201_files/...            (personnel_201_files values already include it)
 *   disciplinary_actions/... (reinlist_disciplinary_actions.file_path)
 *   selfies/...              (users.selfie_image)
 */
class FileController extends Controller
{
    // personnel_data columns that hold file paths (ApplicantDocumentController::store)
    private const DOCUMENT_COLUMNS = [
        'soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr',
        'medical_clearance', 'pft_clearance', 'whole_body_picture', 'photo_2x2', 'residential_sketch',
    ];

    // personnel_201_files columns (ApplicantDocumentController::store_201)
    private const FILE_201_COLUMNS = [
        'soi', 'appointment', 'leave', 'awards', 'disciplinary', 'performance',
        'legal', 'academic', 'financial', 'retirement', 'body_picture',
    ];

    public function show(Request $request, string $path)
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            abort(404);
        }

        [$area, $relative] = array_pad(explode('/', $path, 2), 2, '');

        $roots = [
            'documents_toupload'   => config('pcg.upload_root') . '/documents_toupload',
            '201_files'            => config('pcg.upload_root') . '/201_files',
            'disciplinary_actions' => config('pcg.upload_root') . '/disciplinary_actions',
            'selfies'              => config('pcg.selfie_dir'),
        ];

        if (!isset($roots[$area]) || $relative === '') {
            abort(404);
        }

        $session = $request->session();
        $isAdmin = (bool) $session->get('admin_logged_in');
        $isM1 = $session->has('unit_user_id') && $session->get('unit_role') === \App\Models\UnitUser::ROLE_M1;
        $isUnit = $session->has('unit_user_id') && !$isM1;

        if ($isUnit && !$isAdmin) {
            // Own-unit checkers: only the required documents of their unit's personnel.
            if ($area !== 'documents_toupload' || !$this->inUnit($session->get('unit_name'), $relative)) {
                abort(404);
            }
        } elseif (!$isAdmin && !$isM1) {
            if (!Auth::check()) {
                return redirect()->guest(route('login'));
            }

            if (!$this->ownedBy(Auth::user(), $area, $relative, $path)) {
                abort(404);
            }
        }

        $root = realpath($roots[$area]);
        $full = $root ? realpath($root . '/' . $relative) : false;

        // The resolved file must stay inside its area folder.
        if (!$full || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
            abort(404);
        }

        return response()->file($full, [
            'Content-Disposition' => 'inline; filename="' . basename($full) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function inUnit(?string $unit, string $relative): bool
    {
        return DB::table('personnel_data as pd')
            ->join('users as u', 'u.serial_number', '=', 'pd.serial_number')
            ->where('u.mother_unit', $unit)
            ->where(function ($q) use ($relative) {
                foreach (self::DOCUMENT_COLUMNS as $column) {
                    $q->orWhere('pd.' . $column, $relative);
                }
            })
            ->exists();
    }

    private function ownedBy($user, string $area, string $relative, string $path): bool
    {
        switch ($area) {
            case 'documents_toupload':
                return DB::table('personnel_data')
                    ->where('personnel_id', $user->id)
                    ->where(function ($q) use ($relative) {
                        foreach (self::DOCUMENT_COLUMNS as $column) {
                            $q->orWhere($column, $relative);
                        }
                    })
                    ->exists();

            case '201_files':
                return DB::table('personnel_201_files')
                    ->where('personnel_id', $user->id)
                    ->where(function ($q) use ($path) {
                        foreach (self::FILE_201_COLUMNS as $column) {
                            $q->orWhere($column, $path);
                        }
                    })
                    ->exists();

            case 'selfies':
                return $user->selfie_image === $path;

            default:
                // Disciplinary records are for unit checkers and admins only.
                return false;
        }
    }
}
