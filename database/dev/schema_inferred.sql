-- =====================================================================
-- pcg_core — INFERRED development schema (MySQL 8 / MariaDB 10.4+)
-- =====================================================================
-- Reconstructed from application code only (app/Http/Controllers,
-- app/Models, resources/views, app/Console/Commands). The live pcg_core
-- schema was NOT available. Column types, lengths, defaults and indexes
-- are best guesses; names come from code. Anything uncertain is marked
-- "UNSURE:" in a comment.
--
-- For a DEVELOPMENT database only (e.g. pcg_core_dev). Do not load into
-- the production pcg_core database. No DROP statements, no data.
--
-- Conventions used:
--   * every table InnoDB, utf8mb4 / utf8mb4_unicode_ci
--   * ids BIGINT UNSIGNED AUTO_INCREMENT
--   * serial_number VARCHAR(100), indexed (cross-table join key, no FKs)
--   * almost every non-key column NULLable so app inserts never fail
--   * timestamps NULLable (several inserts omit created_at/updated_at)
--   * no foreign keys (the code assumes none; relations are implicit)
--
-- Load:  mariadb -uroot pcg_core_dev < database/dev/schema_inferred.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- ACCOUNTS
-- ---------------------------------------------------------------------

-- source: App\Models\User; Auth\RegisterController, Auth\LoginController,
--   AdminController (users.* + approve/reject), HRAnalyticsController,
--   DashboardController, TempRegistrationController, ProfileController,
--   UnitUserController (join u.), PersonnelAdministrativeTrainingController,
--   PersonnelOperationalTrainingController, CourseStudentController;
--   views admin/dashboard, admin/hr-analytics, applicant/*, unit/dashboard*
CREATE TABLE IF NOT EXISTS `users` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(255) NOT NULL,
    `email`               VARCHAR(255) NOT NULL,
    `email_verified_at`   TIMESTAMP NULL DEFAULT NULL,
    `password`            VARCHAR(255) NOT NULL,
    `remember_token`      VARCHAR(100) NULL DEFAULT NULL,
    `rank`                VARCHAR(50)  NULL DEFAULT NULL,
    `serial_number`       VARCHAR(100) NULL DEFAULT NULL,
    `registration_type`   VARCHAR(50)  NULL DEFAULT NULL COMMENT 'officer | non_officer',
    `selfie_image`        VARCHAR(255) NULL DEFAULT NULL COMMENT 'relative path, e.g. selfies/<hex>.png',
    `date_of_birth`       DATE NULL DEFAULT NULL,
    `date_of_appointment` DATE NULL DEFAULT NULL,
    `ete`                 DATE NULL DEFAULT NULL,
    `mother_unit`         VARCHAR(255) NULL DEFAULT NULL COMMENT 'register form "command" is saved here',
    `sub_unit`            VARCHAR(255) NULL DEFAULT NULL COMMENT 'register form "category" is saved here',
    `unit_assignment`     VARCHAR(255) NULL DEFAULT NULL COMMENT '"<unit> - <sub_unit>"',
    -- UNSURE: `command` and `unit` are never written by RegisterController
    -- (it maps command->mother_unit, unit->unit_assignment), but they are
    -- selected explicitly by CourseStudentController::create (users.command,
    -- users.unit) and HRAnalyticsController (unit). Without them those
    -- queries fail, so they are included as nullable columns.
    `command`             VARCHAR(255) NULL DEFAULT NULL,
    `unit`                VARCHAR(255) NULL DEFAULT NULL,
    -- UNSURE: only used by TrainingCourseStudentController (namespace
    -- App\Http\Controllers\Admin, not routed / not autoloadable).
    `personnel_id`        VARCHAR(100) NULL DEFAULT NULL,
    `status`              VARCHAR(50)  NULL DEFAULT 'Pending' COMMENT 'Pending | Approved | Rejected (mixed case in real data)',
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_serial_number_index` (`serial_number`),
    KEY `users_status_index` (`status`),
    KEY `users_mother_unit_index` (`mother_unit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- NOTE: LoginController reads $user->role ?? 'user'; no role column is
-- needed (Eloquent returns null for a missing attribute).

-- source: App\Models\UnitUser; UnitUserController (login, store),
--   Console\Commands\HealthCheck (is_active, password)
CREATE TABLE IF NOT EXISTS `unit_users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- UNSURE: store() writes the *string* personnel_id into user_id too.
    `user_id`       VARCHAR(100) NULL DEFAULT NULL,
    `personnel_id`  VARCHAR(100) NULL DEFAULT NULL,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `full_name`     VARCHAR(255) NULL DEFAULT NULL,
    `email`         VARCHAR(255) NULL DEFAULT NULL,
    `username`      VARCHAR(100) NULL DEFAULT NULL,
    `password`      VARCHAR(255) NULL DEFAULT NULL COMMENT 'bcrypt',
    `role`          VARCHAR(50)  NULL DEFAULT NULL COMMENT 'unit_checker',
    `unit_name`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'matched against users.mother_unit',
    `unit_code`     VARCHAR(50)  NULL DEFAULT NULL,
    `permissions`   TEXT NULL DEFAULT NULL COMMENT 'JSON (model cast: array)',
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    -- UNSURE: validation uses unique:unit_users,email but the real table
    -- may not enforce it (docs/DATABASE_ARCHITECTURE.md lists it as a
    -- candidate change), so this is a plain index.
    KEY `unit_users_email_index` (`email`),
    KEY `unit_users_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\Profile (PK user_id, non-incrementing);
--   DashboardController::profile/updateProfile, FaceVerificationController::saveResult;
--   view applicant/updated_profile
CREATE TABLE IF NOT EXISTS `profiles` (
    -- UNSURE: model declares keyType 'string', but the value is always
    -- users.id (integer). BIGINT UNSIGNED chosen.
    `user_id`                   BIGINT UNSIGNED NOT NULL,
    `district`                  VARCHAR(255) NULL DEFAULT NULL,
    `first_name`                VARCHAR(255) NULL DEFAULT NULL,
    `middle_name`               VARCHAR(255) NULL DEFAULT NULL,
    `last_name`                 VARCHAR(255) NULL DEFAULT NULL,
    `extension_name`            VARCHAR(10)  NULL DEFAULT NULL,
    `birthday`                  DATE NULL DEFAULT NULL,
    `sex`                       VARCHAR(10)  NULL DEFAULT NULL,
    `photo`                     VARCHAR(255) NULL DEFAULT NULL,
    `perm_street`               VARCHAR(255) NULL DEFAULT NULL,
    `perm_city`                 VARCHAR(255) NULL DEFAULT NULL,
    `perm_province`             VARCHAR(255) NULL DEFAULT NULL,
    `perm_zipcode`              VARCHAR(10)  NULL DEFAULT NULL,
    `perm_years_residency`      VARCHAR(20)  NULL DEFAULT NULL COMMENT 'UNSURE type: validated numeric',
    `pres_street`               VARCHAR(255) NULL DEFAULT NULL,
    `pres_city`                 VARCHAR(255) NULL DEFAULT NULL,
    `pres_province`             VARCHAR(255) NULL DEFAULT NULL,
    `pres_zipcode`              VARCHAR(10)  NULL DEFAULT NULL,
    `pres_years_residency`      VARCHAR(20)  NULL DEFAULT NULL COMMENT 'UNSURE type: validated numeric',
    `education_level`           VARCHAR(255) NULL DEFAULT NULL,
    `tertiary_course`           VARCHAR(255) NULL DEFAULT NULL,
    `tertiary_school`           VARCHAR(255) NULL DEFAULT NULL,
    `tertiary_year_graduated`   VARCHAR(4)   NULL DEFAULT NULL,
    `secondary_school`          VARCHAR(255) NULL DEFAULT NULL,
    `secondary_strand`          VARCHAR(255) NULL DEFAULT NULL,
    `secondary_year_graduated`  VARCHAR(4)   NULL DEFAULT NULL,
    `elementary_school`         VARCHAR(255) NULL DEFAULT NULL,
    `elementary_year_graduated` VARCHAR(4)   NULL DEFAULT NULL,
    `profile_code`              VARCHAR(100) NULL DEFAULT NULL COMMENT '"<year>-<district>-<5 digits>"',
    -- UNSURE: written only by FaceVerificationController::saveResult.
    `face_verified`             TINYINT(1) NULL DEFAULT NULL,
    `face_distance`             DOUBLE NULL DEFAULT NULL,
    `face_verified_at`          TIMESTAMP NULL DEFAULT NULL,
    `created_at`                TIMESTAMP NULL DEFAULT NULL,
    `updated_at`                TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    KEY `profiles_profile_code_index` (`profile_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: FaceVerificationController::saveResult validation rule
--   'exists:applicants,id' (route POST /applicant/face-verify/result).
-- UNSURE: no other code touches this table; it is probably a legacy
-- leftover. Created minimal so the exists-rule query does not error.
CREATE TABLE IF NOT EXISTS `applicants` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- DOCUMENTS AND REVIEW
-- ---------------------------------------------------------------------

-- source: ApplicantDocumentController::store, DashboardController::uploadDocuments,
--   ProfileController::generateSOI (photo_2x2), UnitDocumentController::review,
--   UnitUserController::dashboard/dashboardM1 (pd.*);
--   views applicant/upload-documents, applicant/soi_template, unit/dashboard, unit/dashboard_m1
CREATE TABLE IF NOT EXISTS `personnel_data` (
    `id`                                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`                       BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `serial_number`                      VARCHAR(100) NULL DEFAULT NULL,
    `soi`                                VARCHAR(255) NULL DEFAULT NULL,
    `nbi_clearance`                      VARCHAR(255) NULL DEFAULT NULL,
    `ombudsman_clearance`                VARCHAR(255) NULL DEFAULT NULL,
    `sandiganbayan_clearance`            VARCHAR(255) NULL DEFAULT NULL,
    `ofr`                                VARCHAR(255) NULL DEFAULT NULL,
    `medical_clearance`                  VARCHAR(255) NULL DEFAULT NULL,
    `pft_clearance`                      VARCHAR(255) NULL DEFAULT NULL,
    `whole_body_picture`                 VARCHAR(255) NULL DEFAULT NULL,
    `photo_2x2`                          VARCHAR(255) NULL DEFAULT NULL,
    `residential_sketch`                 VARCHAR(255) NULL DEFAULT NULL,
    `nbi_clearance_expiration`           DATE NULL DEFAULT NULL,
    `ombudsman_clearance_expiration`     DATE NULL DEFAULT NULL,
    `sandiganbayan_clearance_expiration` DATE NULL DEFAULT NULL,
    `latitude`                           DECIMAL(10,7) NULL DEFAULT NULL,
    `longitude`                          DECIMAL(10,7) NULL DEFAULT NULL,
    `status`                             VARCHAR(50) NULL DEFAULT 'Pending' COMMENT 'Pending | approved | rejected (mixed case)',
    `created_at`                         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`                         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `personnel_data_personnel_id_index` (`personnel_id`),
    KEY `personnel_data_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- NOTE: unit/dashboard*.blade.php read $doc->contact_number ?? 'N/A', but the
-- query selects only pd.* (+ u.name, u.serial_number), so it is always N/A.
-- No contact_number column is added here.

-- source: UnitDocumentController::review (insert), ApplicantDocumentController::store
--   (mark complied), DashboardController::index; view applicant/dashboard (doc.doc_type, doc.comment)
CREATE TABLE IF NOT EXISTS `rejected_documents` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `doc_type`      VARCHAR(100) NULL DEFAULT NULL COMMENT 'label, e.g. "NBI Clearance", "2x2 Photo"',
    `file_path`     VARCHAR(255) NULL DEFAULT NULL,
    `comment`       TEXT NULL DEFAULT NULL,
    `status`        VARCHAR(50)  NULL DEFAULT 'rejected' COMMENT 'rejected | complied',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `rejected_documents_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: ApplicantDocumentController::index_201/store_201,
--   UnitUserController::index_201/show_201 (p201.*);
--   views applicant/201_files, unit/201_files_index (data.documents.*)
CREATE TABLE IF NOT EXISTS `personnel_201_files` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `personnel_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `soi`           VARCHAR(255) NULL DEFAULT NULL,
    `appointment`   VARCHAR(255) NULL DEFAULT NULL,
    `leave`         VARCHAR(255) NULL DEFAULT NULL COMMENT 'LEAVE is a reserved word: always quote',
    `awards`        VARCHAR(255) NULL DEFAULT NULL,
    `disciplinary`  VARCHAR(255) NULL DEFAULT NULL,
    `performance`   VARCHAR(255) NULL DEFAULT NULL,
    `legal`         VARCHAR(255) NULL DEFAULT NULL,
    `academic`      VARCHAR(255) NULL DEFAULT NULL,
    `financial`     VARCHAR(255) NULL DEFAULT NULL,
    `retirement`    VARCHAR(255) NULL DEFAULT NULL,
    `body_picture`  VARCHAR(255) NULL DEFAULT NULL,
    -- UNSURE: store_201 never writes timestamps; kept nullable in case they exist.
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `personnel_201_files_personnel_id_index` (`personnel_id`),
    KEY `personnel_201_files_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SOI / RE-ENLISTMENT ("reinlist_*") — keyed by serial_number
-- ---------------------------------------------------------------------

-- source: TempRegistrationController::saveStep1, ReenlistController::store,
--   DashboardController::profile/uploadDocuments (rbi.), ProfileController::generateSOI (b.*),
--   UnitUserController::searchSerial/index_201/show_201 (pi.);
--   views applicant/profile, applicant/soi_template, unit/201_files_index
CREATE TABLE IF NOT EXISTS `reinlist_basic_info` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number`  VARCHAR(100) NULL DEFAULT NULL COMMENT 'updateOrInsert key (UNSURE whether UNIQUE in prod)',
    `last_name`      VARCHAR(255) NULL DEFAULT NULL,
    `first_name`     VARCHAR(255) NULL DEFAULT NULL,
    `middle_name`    VARCHAR(255) NULL DEFAULT NULL,
    `extension_name` VARCHAR(50)  NULL DEFAULT NULL,
    `rank`           VARCHAR(50)  NULL DEFAULT NULL,
    `class`          VARCHAR(50)  NULL DEFAULT NULL,
    `birthday`       DATE NULL DEFAULT NULL,
    `birth_place`    VARCHAR(255) NULL DEFAULT NULL,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_basic_info_serial_number_index` (`serial_number`),
    KEY `reinlist_basic_info_last_name_index` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep2..6 (personnel_id lookup),
--   ReenlistController::store (UNSURE: merges arbitrary request 'personal.*'
--   keys; that endpoint is only called from commented-out JS),
--   DashboardController::profile (rpi.), ProfileController::generateSOI (p.*),
--   CourseStudentController::index/create, UnitUserController dashboards (rpi join);
--   views applicant/profile, applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_personal_info` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`      VARCHAR(100) NULL DEFAULT NULL COMMENT '"MEPCOM-<serial>-<year>"; updateOrInsert key in saveStep2',
    `serial_number`     VARCHAR(100) NULL DEFAULT NULL,
    `nickname`          VARCHAR(100) NULL DEFAULT NULL,
    `sex`               VARCHAR(20)  NULL DEFAULT NULL,
    `civil_status`      VARCHAR(50)  NULL DEFAULT NULL,
    `citizenship`       VARCHAR(100) NULL DEFAULT NULL,
    `religion`          VARCHAR(100) NULL DEFAULT NULL,
    `blood_type`        VARCHAR(10)  NULL DEFAULT NULL,
    -- UNSURE type: measurements are validated "numeric" only on first save;
    -- later saves skip validation, so VARCHAR keeps dev inserts safe.
    `height`            VARCHAR(20)  NULL DEFAULT NULL,
    `weight`            VARCHAR(20)  NULL DEFAULT NULL,
    `head_size`         VARCHAR(20)  NULL DEFAULT NULL,
    `foot_size`         VARCHAR(20)  NULL DEFAULT NULL,
    `waist_size`        VARCHAR(20)  NULL DEFAULT NULL,
    `distinct_features` VARCHAR(255) NULL DEFAULT NULL,
    `body_build`        VARCHAR(50)  NULL DEFAULT NULL,
    `hair_color`        VARCHAR(50)  NULL DEFAULT NULL,
    `skin_color`        VARCHAR(50)  NULL DEFAULT NULL,
    `eye_color`         VARCHAR(50)  NULL DEFAULT NULL,
    `contact_number`    VARCHAR(50)  NULL DEFAULT NULL,
    `email`             VARCHAR(150) NULL DEFAULT NULL,
    `home_address`      VARCHAR(255) NULL DEFAULT NULL,
    `permanent_address` VARCHAR(255) NULL DEFAULT NULL,
    `tin`               VARCHAR(50)  NULL DEFAULT NULL,
    `philhealth`        VARCHAR(50)  NULL DEFAULT NULL,
    `pagibig`           VARCHAR(50)  NULL DEFAULT NULL,
    `gsis`              VARCHAR(50)  NULL DEFAULT NULL,
    `sss`               VARCHAR(50)  NULL DEFAULT NULL,
    `drivers_license`   VARCHAR(50)  NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_personal_info_personnel_id_index` (`personnel_id`),
    KEY `reinlist_personal_info_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep3, ReenlistController::store (no timestamps),
--   DashboardController::profile, ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_dependents` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`  VARCHAR(100) NULL DEFAULT NULL,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `name`          VARCHAR(255) NULL DEFAULT NULL,
    `relationship`  VARCHAR(50)  NULL DEFAULT NULL,
    `birthdate`     DATE NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_dependents_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep4 (level tertiary|masteral|secondary|
--   primary|vocational), ReenlistController::store (period), DashboardController::profile,
--   ProfileController::generateSOI, AdminController::dashboard (MIN(id) subquery),
--   HRAnalyticsController (ra.course); views applicant/soi_template, admin/dashboard
CREATE TABLE IF NOT EXISTS `reinlist_academic` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`  VARCHAR(100) NULL DEFAULT NULL,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `level`         VARCHAR(30)  NULL DEFAULT NULL,
    `course`        VARCHAR(255) NULL DEFAULT NULL,
    `school`        VARCHAR(255) NULL DEFAULT NULL,
    `year_start`    VARCHAR(20)  NULL DEFAULT NULL COMMENT 'year as text (form type=number)',
    `year_end`      VARCHAR(20)  NULL DEFAULT NULL,
    -- UNSURE: only ReenlistController::store writes `period`.
    `period`        VARCHAR(100) NULL DEFAULT NULL,
    `remarks`       TEXT NULL DEFAULT NULL COMMENT 'vocational rows store "authority" here',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_academic_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: ReenlistController::store only (the step-4 flow stores vocational
--   rows in reinlist_academic with level='vocational').
CREATE TABLE IF NOT EXISTS `reinlist_vocational` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`  VARCHAR(100) NULL DEFAULT NULL COMMENT 'UNSURE: not referenced, added for symmetry',
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `course`        VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `school`        VARCHAR(255) NULL DEFAULT NULL,
    `period`        VARCHAR(100) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_vocational_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep4 (category coastguard|others_local|
--   others_foreign|military|military_others_local|military_others_foreign),
--   ReenlistController::store, DashboardController::profile, ProfileController::generateSOI;
--   view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_trainings` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`  VARCHAR(100) NULL DEFAULT NULL,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `category`      VARCHAR(50)  NULL DEFAULT NULL,
    `course`        VARCHAR(255) NULL DEFAULT NULL,
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `school`        VARCHAR(255) NULL DEFAULT NULL,
    `period`        VARCHAR(100) NULL DEFAULT NULL,
    `remarks`       TEXT NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_trainings_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep5 (type shipboard|shipboard_collateral|
--   shorebased|shorebased_collateral|special|govt|non_govt; delete by applicant_id),
--   ReenlistController::store, DashboardController::profile, ProfileController::generateSOI;
--   views applicant/profile, applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_duties` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  VARCHAR(100) NULL DEFAULT NULL COMMENT 'holds the SERIAL NUMBER here (not users.id)',
    `type`          VARCHAR(50)  NULL DEFAULT NULL,
    `unit`          VARCHAR(255) NULL DEFAULT NULL,
    `position`      VARCHAR(255) NULL DEFAULT NULL,
    `agency`        VARCHAR(255) NULL DEFAULT NULL,
    `period`        VARCHAR(100) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_duties_serial_number_index` (`serial_number`),
    KEY `reinlist_duties_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep5, ReenlistController::store,
--   DashboardController::profile (orderBy date), ProfileController::generateSOI;
--   view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_appointments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  VARCHAR(100) NULL DEFAULT NULL COMMENT 'holds the SERIAL NUMBER here (not users.id)',
    `description`   TEXT NULL DEFAULT NULL,
    `date`          DATE NULL DEFAULT NULL COMMENT 'form input type=date',
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_appointments_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (delete by applicant_id = users.id),
--   ReenlistController::store, DashboardController::profile, ProfileController::generateSOI;
--   view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_careers` (
    `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number`             VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`              BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `student`                   VARCHAR(255) NULL DEFAULT NULL,
    `sea`                       VARCHAR(255) NULL DEFAULT NULL,
    `staff`                     VARCHAR(255) NULL DEFAULT NULL,
    `instructor`                VARCHAR(255) NULL DEFAULT NULL,
    `command`                   VARCHAR(255) NULL DEFAULT NULL,
    `organization_affiliation`  VARCHAR(255) NULL DEFAULT NULL,
    `civil_service_eligibility` VARCHAR(255) NULL DEFAULT NULL,
    `compulsory_retirement`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'UNSURE: may be a DATE in prod; free text is safer',
    `created_at`                TIMESTAMP NULL DEFAULT NULL,
    `updated_at`                TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_careers_serial_number_index` (`serial_number`),
    KEY `reinlist_careers_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (militaryAwards: branch -> authority),
--   DashboardController::profile, ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_awards` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `category`      VARCHAR(255) NULL DEFAULT NULL,
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_awards_serial_number_index` (`serial_number`),
    KEY `reinlist_awards_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The following ten tables share one shape: (serial_number, applicant_id, type, authority).

-- source: TempRegistrationController::saveStep6 (cgAwardsDecorations),
--   DashboardController::profile, ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_cg_awards_decorations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_cg_awards_decorations_serial_number_index` (`serial_number`),
    KEY `reinlist_cg_awards_decorations_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (cgLetters), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_cg_letters` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_cg_letters_serial_number_index` (`serial_number`),
    KEY `reinlist_cg_letters_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: DashboardController::profile only (read; reads `type` only — no writer exists).
CREATE TABLE IF NOT EXISTS `reinlist_cg_certificates` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'UNSURE: assumed like sibling tables',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'UNSURE: not referenced',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_cg_certificates_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (cgAppreciations), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_cg_appreciations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_cg_appreciations_serial_number_index` (`serial_number`),
    KEY `reinlist_cg_appreciations_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (cgPlaques), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_cg_plaques` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_cg_plaques_serial_number_index` (`serial_number`),
    KEY `reinlist_cg_plaques_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: DashboardController::profile only (read; type, authority — no writer exists).
CREATE TABLE IF NOT EXISTS `reinlist_military_letters` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'UNSURE: assumed like sibling tables',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_military_letters_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (civilianCommendations),
--   DashboardController::profile (otherLetterCommendations), ProfileController::generateSOI;
--   view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_civilian_awards` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_civilian_awards_serial_number_index` (`serial_number`),
    KEY `reinlist_civilian_awards_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (otherLetters), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_other_letters` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_other_letters_serial_number_index` (`serial_number`),
    KEY `reinlist_other_letters_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (otherAppreciations), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_other_appreciations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_other_appreciations_serial_number_index` (`serial_number`),
    KEY `reinlist_other_appreciations_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: TempRegistrationController::saveStep6 (otherPlaques), DashboardController::profile,
--   ProfileController::generateSOI; view applicant/soi_template
CREATE TABLE IF NOT EXISTS `reinlist_other_plaques` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `applicant_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '= users.id',
    `type`          VARCHAR(255) NULL DEFAULT NULL,
    `authority`     VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_other_plaques_serial_number_index` (`serial_number`),
    KEY `reinlist_other_plaques_applicant_id_index` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: ReenlistController::store only (updateOrInsert by serial_number; no created_at written).
--   applicant/profile reads organization/eligibility/retirement off $reinlistData (?? ''),
--   but DashboardController never loads this table.
CREATE TABLE IF NOT EXISTS `reinlist_profile` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `organization`  VARCHAR(255) NULL DEFAULT NULL,
    `eligibility`   VARCHAR(255) NULL DEFAULT NULL,
    `retirement`    VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_profile_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: UnitUserController::storeDisciplinary/deleteDisciplinary
CREATE TABLE IF NOT EXISTS `reinlist_disciplinary_actions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `file_path`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'relative to <upload_root>/disciplinary_actions/',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reinlist_disciplinary_actions_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TRAINING TRACKS / LMS
-- ---------------------------------------------------------------------

-- source: App\Models\AdministrativeTrack; AdministrativeTrackController,
--   ApplicantTrainingController (DB::table($table)), PersonnelAdministrativeTrainingController (at.);
--   views administrative_tracks/show, applicant/training/*, personnel_administrative_training/index
CREATE TABLE IF NOT EXISTS `administrative_tracks` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `no`              INT NULL DEFAULT NULL,
    `division_unit`   VARCHAR(255) NULL DEFAULT NULL,
    `rank`            VARCHAR(100) NULL DEFAULT NULL COMMENT 'e.g. "E-1 (Apprentice Seaman)"; matched with LIKE %E-1%',
    `year_in_grade`   INT NULL DEFAULT NULL,
    `course_training` VARCHAR(255) NULL DEFAULT NULL,
    `provider`        VARCHAR(255) NULL DEFAULT NULL,
    `status`          VARCHAR(100) NULL DEFAULT NULL,
    `remarks`         TEXT NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `administrative_tracks_rank_index` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\OperationTrack; OperationTrackController (CRUD),
--   ApplicantTrainingController, PersonnelOperationalTrainingController;
--   views operation_tracks/index, personnel/operational_training, applicant/training/*
CREATE TABLE IF NOT EXISTS `operation_tracks` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `no`              INT NULL DEFAULT NULL,
    `division_unit`   VARCHAR(255) NULL DEFAULT NULL,
    `rank`            VARCHAR(100) NULL DEFAULT NULL,
    `year_in_grade`   INT NULL DEFAULT NULL,
    `course_training` VARCHAR(255) NULL DEFAULT NULL,
    `provider`        VARCHAR(255) NULL DEFAULT NULL,
    `status`          VARCHAR(100) NULL DEFAULT NULL,
    `remarks`         TEXT NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `operation_tracks_rank_index` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: PersonnelAdministrativeTrainingController (re.user_rank = u.rank,
--   LOWER(TRIM(re.track_rank)) = LOWER(TRIM(at.rank)))
CREATE TABLE IF NOT EXISTS `rank_equivalencies` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_rank`  VARCHAR(50)  NULL DEFAULT NULL COMMENT 'users.rank, e.g. SN1',
    `track_rank` VARCHAR(100) NULL DEFAULT NULL COMMENT 'administrative_tracks.rank, e.g. "E-3 (Seaman 1st Class)"',
    -- UNSURE: timestamps not referenced.
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `rank_equivalencies_user_rank_index` (`user_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\TrainingApplication; ApplicantTrainingController (leftJoin + create),
--   TrainingApplicationController (index/updateStatus, DATE_FORMAT(COALESCE(applied_at, created_at)));
--   views admin/training-applications/index, applicant/training/index
CREATE TABLE IF NOT EXISTS `training_applications` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial_number`   VARCHAR(100) NULL DEFAULT NULL,
    `training_type`   VARCHAR(10)  NULL DEFAULT NULL COMMENT 'ACT | OCT',
    `track_id`        BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'administrative_tracks.id or operation_tracks.id by training_type',
    `course_training` VARCHAR(255) NULL DEFAULT NULL,
    `rank`            VARCHAR(100) NULL DEFAULT NULL,
    `division_unit`   VARCHAR(255) NULL DEFAULT NULL,
    `year_in_grade`   INT NULL DEFAULT NULL,
    `provider`        VARCHAR(255) NULL DEFAULT NULL,
    `remarks`         TEXT NULL DEFAULT NULL,
    `status`          VARCHAR(50)  NULL DEFAULT 'Pending' COMMENT 'Pending | Approved | Rejected | Completed',
    `applied_at`      TIMESTAMP NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `training_applications_serial_number_index` (`serial_number`),
    KEY `training_applications_track_index` (`training_type`, `track_id`),
    KEY `training_applications_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\TrainingCourse; TrainingCourseController (CRUD, withCount students),
--   CourseStudentController; views admin/training_courses/*
CREATE TABLE IF NOT EXISTS `training_courses` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'app validates unique:training_courses,course_name',
    `description` TEXT NULL DEFAULT NULL,
    `status`      VARCHAR(50)  NULL DEFAULT 'Active' COMMENT 'Active | Inactive',
    `created_at`  TIMESTAMP NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `training_courses_course_name_index` (`course_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\CourseStudent; CourseStudentController (index/store/importCsv/destroy),
--   DashboardController::index (where serial_number); views admin/training_courses/students/*,
--   applicant/dashboard ($hasClass)
CREATE TABLE IF NOT EXISTS `course_students` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'training_courses.id',
    -- UNSURE: user_id is filtered on (where user_id = ?) and passed to
    -- create(), but it is NOT in the model's $fillable, so Eloquent drops it
    -- and it is always NULL on insert. Kept nullable so queries work.
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `serial_number` VARCHAR(100) NULL DEFAULT NULL,
    `full_name`     VARCHAR(255) NULL DEFAULT NULL,
    `date_attended` DATE NULL DEFAULT NULL COMMENT 'model cast: date',
    `date_ended`    DATE NULL DEFAULT NULL COMMENT 'model cast: date',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `course_students_course_id_index` (`course_id`),
    KEY `course_students_user_id_index` (`user_id`),
    KEY `course_students_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: DashboardController::profile (DB::table('courses')->get());
--   view applicant/profile (course_description used as <option> value/label)
CREATE TABLE IF NOT EXISTS `courses` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_description` VARCHAR(255) NULL DEFAULT NULL,
    -- UNSURE: only course_description is referenced; timestamps assumed.
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: App\Models\OsaertTrainee; OsaertController (index, bulkUpload, verify,
--   certificates, verifyCertificate); views admin/osaert/index, certificates, verify-certificate
CREATE TABLE IF NOT EXISTS `osaert_trainees` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255) NULL DEFAULT NULL,
    `company`       VARCHAR(255) NULL DEFAULT NULL,
    -- Stored as STRINGS: filtered with RIGHT(col, 4) = year and compared to ''.
    -- NOTE: bulkUpload writes 'Y-m-d' (RIGHT(...,4) then yields 'MM-DD'),
    -- while RIGHT() filters assume 'dd/mm/YYYY' legacy values; verify() uses
    -- whereYear(). The real stored format is unknown.
    `date_attended` VARCHAR(20) NULL DEFAULT NULL,
    `date_ended`    VARCHAR(20) NULL DEFAULT NULL,
    -- UNSURE: read only by admin/osaert/verify-certificate ($trainee->start_year,
    -- $trainee->end_year, substr(..., -4)); never written by code. Possibly
    -- legacy columns; included so the certificate can show values.
    `start_year`    VARCHAR(20) NULL DEFAULT NULL,
    `end_year`      VARCHAR(20) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `osaert_trainees_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LARAVEL FRAMEWORK TABLES (shapes from database/migrations 2014/2019)
-- ---------------------------------------------------------------------

-- source: database/migrations/2014_10_12_100000 (password broker; no reset routes exist today)
CREATE TABLE IF NOT EXISTS `password_resets` (
    `email`      VARCHAR(255) NOT NULL,
    `token`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: database/migrations/2019_08_19_000000; config/queue.php failed-job store
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`       VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue`      TEXT NOT NULL,
    `payload`    LONGTEXT NOT NULL,
    `exception`  LONGTEXT NOT NULL,
    `failed_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source: database/migrations/2019_12_14_000001; App\Models\User uses Sanctum HasApiTokens
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id`   BIGINT UNSIGNED NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `token`          VARCHAR(64) NOT NULL,
    `abilities`      TEXT NULL DEFAULT NULL,
    `last_used_at`   TIMESTAMP NULL DEFAULT NULL,
    `expires_at`     TIMESTAMP NULL DEFAULT NULL,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Not created: `services` (migrations only, unused by code), `sessions`,
-- `migrations`, `jobs`, `cache`.
