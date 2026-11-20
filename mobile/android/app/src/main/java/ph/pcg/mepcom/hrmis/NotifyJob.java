package ph.pcg.mepcom.hrmis;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.job.JobInfo;
import android.app.job.JobParameters;
import android.app.job.JobScheduler;
import android.app.job.JobService;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * Phone alerts for portal notifications (1.0.3, owner request 2026-11-20). No push
 * service: every 15 minutes (the shortest JobScheduler period), with a network,
 * the app asks BASE_URL/api/app/notifications for anything newer than the last
 * id it showed, using the account id and token the portal page gave it
 * (meta pcg-app-notify, see MainActivity). Signing out, or a 401, forgets the
 * token and stops the job. Taps open the portal's /notifications page.
 */
public final class NotifyJob extends JobService {
    static final int JOB_ID = 8301;
    private static final String PREFS = "notify";
    private static final String CHANNEL = "hrmis-updates";

    private Thread worker;

    // ---------------------------------------------------------- token handling (from MainActivity)

    /** Saves "id:token" from the page and starts the job; an empty value forgets it. */
    static void setToken(Context ctx, String value) {
        SharedPreferences p = ctx.getSharedPreferences(PREFS, MODE_PRIVATE);
        String old = p.getString("token", "");
        if (value == null || !value.matches("\\d+:[0-9a-f]{64}")) {
            if (!old.isEmpty()) {
                p.edit().clear().apply();
                JobScheduler js = ctx.getSystemService(JobScheduler.class);
                if (js != null) {
                    js.cancel(JOB_ID);
                }
            }
            return;
        }
        if (!value.equals(old)) {
            // a new account (or a new password): start from "now" so old items are not replayed
            p.edit().putString("token", value).putLong("after", -1).apply();
        }
        schedule(ctx);
    }

    static void schedule(Context ctx) {
        JobScheduler js = ctx.getSystemService(JobScheduler.class);
        if (js == null || js.getPendingJob(JOB_ID) != null) {
            return;
        }
        js.schedule(new JobInfo.Builder(JOB_ID, new ComponentName(ctx, NotifyJob.class))
            .setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY)
            .setPeriodic(15 * 60 * 1000L)
            .setPersisted(false)
            .build());
    }

    // ---------------------------------------------------------- the job

    @Override
    public boolean onStartJob(JobParameters params) {
        worker = new Thread(() -> {
            try {
                poll(getApplicationContext());
            } catch (Exception ignored) {
                // network or server trouble: try again next period
            }
            jobFinished(params, false);
        });
        worker.start();
        return true;
    }

    @Override
    public boolean onStopJob(JobParameters params) {
        if (worker != null) {
            worker.interrupt();
        }
        return true;
    }

    private static void poll(Context ctx) throws Exception {
        SharedPreferences p = ctx.getSharedPreferences(PREFS, MODE_PRIVATE);
        String token = p.getString("token", "");
        int colon = token.indexOf(':');
        if (colon < 1) {
            return;
        }
        long after = p.getLong("after", -1);
        Uri uri = Uri.parse(BuildConfig.BASE_URL + "/api/app/notifications").buildUpon()
            .appendQueryParameter("u", token.substring(0, colon))
            .appendQueryParameter("t", token.substring(colon + 1))
            .appendQueryParameter("after", String.valueOf(Math.max(after, 0)))
            .build();
        HttpURLConnection c = (HttpURLConnection) new URL(uri.toString()).openConnection();
        c.setConnectTimeout(15000);
        c.setReadTimeout(15000);
        c.setRequestProperty("Accept", "application/json");
        c.setRequestProperty("User-Agent", "PCGHRMIS-Android/" + BuildConfig.VERSION_NAME);
        int status = c.getResponseCode();
        if (status == 401) {
            setToken(ctx, "");                      // password changed or account no longer approved
            return;
        }
        if (status != 200) {
            return;
        }
        String body;
        try (InputStream in = c.getInputStream(); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] buf = new byte[4096];
            int n;
            while ((n = in.read(buf)) > 0 && out.size() < 64 * 1024) {
                out.write(buf, 0, n);
            }
            body = out.toString("UTF-8");
        } finally {
            c.disconnect();
        }
        JSONArray items = new JSONObject(body).optJSONArray("items");
        if (items == null || items.length() == 0) {
            if (after < 0) {
                p.edit().putLong("after", 0).apply();
            }
            return;
        }
        long newest = items.getJSONObject(0).optLong("id", 0);
        if (after >= 0) {                            // first poll after sign-in only sets the starting point
            for (int i = items.length() - 1; i >= 0; i--) {
                JSONObject it = items.getJSONObject(i);
                show(ctx, (int) it.optLong("id"), it.optString("title", ""), it.optString("body", ""));
            }
        }
        p.edit().putLong("after", Math.max(newest, Math.max(after, 0))).apply();
    }

    private static void show(Context ctx, int id, String title, String text) {
        if (title.isEmpty()) {
            return;
        }
        if (Build.VERSION.SDK_INT >= 33
            && ctx.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        NotificationManager nm = ctx.getSystemService(NotificationManager.class);
        if (nm == null) {
            return;
        }
        if (Build.VERSION.SDK_INT >= 26) {
            nm.createNotificationChannel(new NotificationChannel(CHANNEL, ctx.getString(R.string.notify_channel), NotificationManager.IMPORTANCE_DEFAULT));
        }
        Intent open = new Intent(ctx, MainActivity.class).putExtra("path", "/notifications")
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent tap = PendingIntent.getActivity(ctx, id, open, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
        android.app.Notification.Builder b = Build.VERSION.SDK_INT >= 26
            ? new android.app.Notification.Builder(ctx, CHANNEL) : new android.app.Notification.Builder(ctx);
        b.setSmallIcon(R.drawable.ic_stat_notify)
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(new android.app.Notification.BigTextStyle().bigText(text))
            .setAutoCancel(true)
            .setContentIntent(tap)
            .setColor(ctx.getColor(R.color.navy));
        nm.notify(id, b.build());
    }
}
