-- ------------------------------------------------------------------
-- PCG_CORE read-only schema inspection
-- Run in phpMyAdmin (SQL tab, database pcg_core selected) or:
--   mysql -u <user> -p pcg_core < database/audit/inspect_schema.sql > schema_report.txt
-- Every statement is a SELECT/SHOW against information_schema.
-- Nothing here changes data or structure.
-- ------------------------------------------------------------------

-- 1. Server engine and version
SELECT VERSION() AS server_version, @@version_comment AS distribution,
       @@character_set_server AS server_charset, @@collation_server AS server_collation;

-- 2. Tables: engine, row estimate, size, collation
SELECT TABLE_NAME, ENGINE, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 2) AS size_mb,
       TABLE_COLLATION, CREATE_TIME, UPDATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- 3. Columns
SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       COLUMN_KEY, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 4. Indexes
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 5. Foreign keys
SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
       r.UPDATE_RULE, r.DELETE_RULE, k.CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL;

-- 6. Views, triggers, routines
SELECT TABLE_NAME AS view_name FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE();
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_TIMING
FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();
SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE();

-- 7. serial_number columns without an index (join key used across the app)
SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.COLLATION_NAME
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.STATISTICS s
  ON s.TABLE_SCHEMA = c.TABLE_SCHEMA AND s.TABLE_NAME = c.TABLE_NAME
 AND s.COLUMN_NAME = c.COLUMN_NAME AND s.SEQ_IN_INDEX = 1
WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME IN ('serial_number', 'personnel_id', 'user_id', 'course_id')
  AND s.INDEX_NAME IS NULL
ORDER BY c.TABLE_NAME;

-- 8. Mixed collations on serial_number (breaks index use in joins)
SELECT COLLATION_NAME, COUNT(*) AS tables_using
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'serial_number'
GROUP BY COLLATION_NAME;

-- 9. Data-quality checks used by the login and review flows (counts only)
SELECT status, COUNT(*) AS users FROM users GROUP BY status;
SELECT COUNT(*) AS unit_users_without_password FROM unit_users WHERE password IS NULL OR password = '';
SELECT COUNT(*) AS unit_users_non_bcrypt_password FROM unit_users
 WHERE password IS NOT NULL AND password <> '' AND password NOT LIKE '$2y$%';
SELECT serial_number, COUNT(*) AS n FROM users GROUP BY serial_number HAVING n > 1;
SELECT COUNT(*) AS personnel_data_without_user FROM personnel_data pd
 LEFT JOIN users u ON u.serial_number = pd.serial_number WHERE u.id IS NULL;
