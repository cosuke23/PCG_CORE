# Uploaded-file encryption

Every file people upload is encrypted on the server with **AES-256-GCM** before
it is written to disk. This covers clearances and other required documents,
201 files, disciplinary records, registration selfies, profile photos and the
HR 201 repository. Files are decrypted only inside the application, after the
viewer's rights have been checked, and every view or download is logged.

| Requirement | How it is met |
|---|---|
| AES-256-GCM application-level encryption | `App\Support\FileCrypto`: per-file random 256-bit data key, 1 MiB chunks, each with its own random nonce and 128-bit authentication tag |
| Private storage outside the public directory | `PCG_UPLOAD_ROOT`, `PCG_SELFIE_DIR` and `PCG_DOCUMENT_ROOT` point outside `public/` (Hermes: `/srv/mepcom-hrmis/shared/uploads` and `shared/storage/app/private`). `pcg:check` **fails** if uploads or selfies are under `public/`. Profile photos no longer use the public disk. |
| Keys kept separate from the files | Master keys live in one key ring file (`PCG_FILE_KEYS_PATH`, Hermes: `shared/keys/file-keys.json`). The app refuses a key ring inside an upload folder or `public/`; it is not in the nightly file backup; it has its own offline backup. |
| Authentication and role-based authorization before decryption | `FileController` (`/files/{path}`) and `PersonnelDocumentController::download` run the existing access rules first (owner, own-unit checker, M1 checker, admin role with `documents.view` / `documents.restricted`, org scope). Only then is the file decrypted. |
| Audit logs for viewing and downloading | `SecureFiles::respond` writes `file.view` / `file.download` to the audit log channel for every viewer, and to `admin_audit_logs` (the Admin → Audit log page) for admins. HR 201 downloads are also in `document_access_logs`. Only ids and the file path are logged, never content. |
| Secure PDF streaming through Laravel controllers | Decrypted chunk by chunk into a `StreamedResponse`. Each chunk is authenticated before it is sent. Responses are sent with `Cache-Control: private, no-store`, `nosniff`, `noindex`, `no-referrer`, and the content type is detected from the decrypted bytes. Only PDF, JPEG and PNG are shown inline; anything else is downloaded. `?download=1` (or the HR download button) forces a download. |
| No public original or encrypted paths | Upload folders are outside the web root, and nginx serves only `public/`. Links are always `/files/...` or `/admin/documents/{id}/download`. Stored names are random. |
| Key rotation and backup / recovery | `pcg:file-keys rotate` → `pcg:files rewrap --apply` → `pcg:file-keys retire <old>`; `pcg:file-keys backup`; `pcg:files verify`. The procedures are below. |

## How it works

```
upload ──► AES-256-GCM with a new random data key (DEK) ──► encrypted file on disk
                     │
                     └─ DEK wrapped (AES-256-GCM) with the active master key (KEK)
                        and stored in the file header with the KEK's id
```

- **Envelope encryption.** Each file has its own data key. The file header
  holds that key encrypted ("wrapped") with a master key from the key ring,
  plus the master key's id. Rotating a master key re-wraps the 60-byte header
  only, so the file content is never re-encrypted.
- **Bound to its place.** Each chunk's authenticated data includes the file
  id, the chunk number, a last-chunk flag, the plaintext size and the file's
  logical path (for example `documents_toupload/documents/123_x.pdf`). These
  changes all make decryption fail:
  - copying an encrypted file over another record's file;
  - reordering, truncating or editing chunks;
  - using the wrong master key.

  A file that fails is never served: the viewer gets an error and the event is
  logged.
- **File layout:**
  - header: `PCGF` 0x01, key id, file id (16 bytes), plaintext size, chunk
    size, wrap nonce and tag, wrapped key (32 bytes);
  - then for each chunk: last flag, nonce (12 bytes), length, ciphertext,
    tag (16 bytes).
- **Database.** Paths, sizes and SHA-256 checksums describe the original
  file, so nothing in the database changed (no migration).
- **Older files** written before this change are still served, after the same
  checks, until `php artisan pcg:files encrypt --apply` converts them.

Code: `app/Support/FileCrypto.php` (format and cipher), `FileKeys.php` (key
ring), `SecureFiles.php` (storing, streaming, auditing, list of folders),
`app/Console/Commands/FileKeysCommand.php`, `FilesCommand.php`.

## Commands

| Command | What it does |
|---|---|
| `php artisan pcg:file-keys init` | Create the key ring (once). Refuses if one exists, or if encrypted files exist without one (restore instead). |
| `php artisan pcg:file-keys status` | Key ring location, permissions and key ids (never the keys). |
| `php artisan pcg:file-keys rotate` | Add a new master key and make it active. |
| `php artisan pcg:file-keys retire <id>` | Remove an old key once no file uses it. |
| `php artisan pcg:file-keys backup --to=<file>` | Copy the key ring (mode 0600) for offline safe keeping. |
| `php artisan pcg:files status` | Encrypted / not encrypted files per folder and per key. |
| `php artisan pcg:files encrypt [--apply]` | Encrypt older files in place. Each one is verified to decrypt to the same SHA-256 before it replaces the original. Old profile photos are moved off the public disk. |
| `php artisan pcg:files verify` | Decrypt every file to prove that the keys and files are intact. |
| `php artisan pcg:files rewrap [--apply]` | Move every file to the active master key after a rotation. |

On Hermes, run them from `/srv/mepcom-hrmis/current` as the deploy account.

## Procedures

### 1. First switch-on (Hermes)

1. Deploy as usual (`deploy\deploy.ps1`). The release adds
   `PCG_FILE_KEYS_PATH` and `PCG_FILE_KEYS_MODE=0640` to an older `.env` and
   creates `shared/keys/`.
   - **Since 2026-11-06 the release never creates a key ring itself.** If none
     can be loaded, it stops before switching.
   - On a brand-new installation, run `php artisan pcg:file-keys init` once
     in the new release folder, then deploy again.
   - On Hermes the key ring already exists (created on 2026-11-05).
   - From the moment a key ring exists, new uploads are encrypted.
2. **Back up the key ring off the server immediately** (procedure 2).
3. Server owner, once: re-run `deploy/server-setup.sh` as root. It puts the
   key ring in its own `mepcom-keys` group: PHP-FPM and the deploy account
   can read it, nginx cannot. It also labels the folder for SELinux.
   Alternatively, for the label only, run
   `semanage fcontext -a -t httpd_sys_content_t "/srv/mepcom-hrmis/shared/keys(/.*)?" && restorecon -Rv /srv/mepcom-hrmis/shared/keys`.
   Then check that `php artisan pcg:check` passes.
4. Make a normal backup (`deploy/backup.sh`).
5. Run `php artisan pcg:files encrypt` (dry run), then `php artisan pcg:files encrypt --apply`.
6. Run `php artisan pcg:files status` until it reports that every stored file
   is encrypted, then `php artisan pcg:files verify`.

On the Windows PC: the key ring defaults to `storage/app/keys/file-keys.json`.
Set `PCG_FILE_KEYS_PATH` to a folder outside the project and outside the
upload folders, for example `C:/pcg-keys/file-keys.json`, then run
`php artisan pcg:file-keys init` and follow steps 2 and 4–6.

### 2. Back up the keys (after init and after every rotation)

- **Without the key ring, the files cannot be decrypted by anyone, including us.**
- The nightly `deploy/backup.sh` deliberately does **not** include the keys.
  It records only the key ids the files need (`file-key-ids.txt`).
- Back up the key ring itself like this:
  1. `php artisan pcg:file-keys backup --to=/home/<deploy>/file-keys-YYYYMMDD.json`.
  2. Copy that file off the server, for example with `scp` to the PC, onto two
     encrypted USB drives (BitLocker or VeraCrypt). They are held by two named
     custodians, for example the system administrator and the DPO, in
     different places.
  3. Delete the copy in the home folder.
  4. Write in the custody log: date, key ids, custodians.
- Keep retired keys in the offline backup until every file backup that may
  still need them has expired (14 days on Hermes, longer for any off-site
  archive).

### 3. Restore (server rebuilt, or files restored from a backup)

1. Restore the database and `files.tar.gz` as in DEPLOYMENT_GUIDE.
2. Put the key ring from the offline backup at `PCG_FILE_KEYS_PATH`:
   - owner: the deploy account;
   - group: the app's group;
   - mode: `0640` on Hermes (`0600` elsewhere).
3. `php artisan pcg:file-keys status`: the ids must include every id in the
   backup's `file-key-ids.txt`.
4. `php artisan pcg:files verify` must report no failures. If any fail, the
   matching key is missing or the file is damaged; restore that file from an
   older backup.

Test this restore on a copy at least twice a year, and after every rotation.

### 4. Rotate the master key (yearly, when a custodian leaves, or at once if a key may be exposed)

1. `php artisan pcg:file-keys rotate`: new uploads now use the new key.
2. Back up the key ring (procedure 2).
3. `php artisan pcg:files rewrap --apply`: every file moves to the new key;
   only the headers change.
4. `php artisan pcg:files status`: every file should now show the new key id.
5. `php artisan pcg:file-keys retire <old id>`: refused while any file still
   uses it.
6. If the old key may have been exposed: also delete it from the offline
   backups once the file backups that use it have expired, and record the
   incident (Data Privacy Act breach procedure, if personal data may have been
   exposed).

## Limits and notes

- **What it protects against:** a stolen disk, backup or copied upload folder
  without the key ring. It does not protect against someone who controls the
  running server: they can read the key ring like the app does. Keep server
  access minimal (SECURITY_AUDIT).
- **Downloads are plaintext.** Once a person downloads a file, their copy is
  not encrypted. The audit log records who downloaded it.
- **Hashes:** the SHA-256 in `personnel_documents` is of the original file, so
  integrity checks and duplicate detection still work.
- **Size:** each file is about 0.003% larger (about 33 bytes per MiB, plus
  about 100 bytes of header), and it uses no extra memory while streaming.
- **Tests** use `PCG_FILE_KEY` (phpunit.xml) instead of a key ring file. Never
  set `PCG_FILE_KEY` on a server.
