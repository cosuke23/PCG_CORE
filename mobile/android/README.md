# PCG HRMIS for Android

An Android app that opens the PCG HRMIS member portal full-screen. All screens,
rules and data stay in the Laravel app; the app adds the phone features the portal
needs. Members whose SOI is submitted download it from the portal at `/mobile-app`.

- Opens only the host in `hrmis.baseUrl`, over HTTPS with system certificates.
  Links to any other site open in the phone's browser.
- Starts at `/dashboard` (the portal sends signed-out members to `/login`).
- Camera (registration selfie), location (residence pin), file picking (uploads),
  downloads and printing (`window.print()`, used by Print my SOI) work for that host only.
- No backups of app data; no cleartext traffic; Android 7.0 (API 24) or newer.

## Build (Windows)

Needs JDK 17+ (21 used) and the Android SDK (platform 35, build-tools 35).

1. `hrmis.properties` (git-ignored, see `hrmis.properties.example`):
   `hrmis.baseUrl=https://<the PCG HRMIS host>`
2. `local.properties` (git-ignored): `sdk.dir=C:/Users/<you>/Android/sdk`
3. Release signing: a `keystore.properties` file kept **outside** the repo (see
   `keystore.properties.example`), named by `HRMIS_KEYSTORE_PROPERTIES`.
4. Build:

   ```powershell
   $env:JAVA_HOME = 'C:\Users\<you>\Android\jdk-21'
   $env:HRMIS_KEYSTORE_PROPERTIES = 'C:\Users\<you>\Android\keys\keystore.properties'
   .\gradlew.bat lintRelease assembleRelease
   ```

   Output: `app/build/outputs/apk/release/app-release.apk`. Check it with
   `apksigner verify --print-certs app\build\outputs\apk\release\app-release.apk`.

## Publish a version

1. Raise `versionCode` (by 1) and `versionName` in `app/build.gradle`, build.
2. Copy the APK to the server as
   `/srv/mepcom-hrmis/shared/storage/app/private/mobile/pcg-hrmis.apk`
   (or the path in `PCG_ANDROID_APK`) and set `PCG_ANDROID_APK_VERSION` in the server `.env`.
3. Members install the new APK over the old one; their sign-in is kept.

## Signing key

Phones accept an update only if it is signed with the same key as the installed
app. Keep `pcg-hrmis-release.jks` and its passwords backed up somewhere safe and
never commit them. If the key is lost, every member has to uninstall the app and
install a newly signed one.
