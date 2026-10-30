# Legacy migrations — not run

These are the migrations that shipped with the project. They do **not**
describe the real `pcg_core` schema (it was built by hand in phpMyAdmin), and
two of them create `users` / `services` a second time, so running them fails.
Laravel only loads migrations from `database/migrations/` itself, not from
sub-folders, so they are kept here for reference only.

The schema the code actually uses is documented in
`docs/DATABASE_ARCHITECTURE.md` and reconstructed in
`database/dev/schema_inferred.sql`.
