package ph.pcg.mepcom.hrmis;

import android.Manifest;
import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.Insets;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.print.PrintAttributes;
import android.print.PrintManager;
import android.content.res.ColorStateList;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowInsets;
import android.webkit.CookieManager;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.PermissionRequest;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;
import android.window.OnBackInvokedDispatcher;

/**
 * PCG HRMIS for Android: the member portal of PCG HRMIS (MEPCOM HRMIS) in a
 * full-screen WebView. It opens only BuildConfig.BASE_URL over HTTPS; links to
 * any other site open in the phone's browser. Camera (registration selfie),
 * location (residence pin), file picking (uploads), downloads and printing
 * (Print my SOI) are passed through to the page only for that host.
 */
public class MainActivity extends Activity {

    private static final int REQ_FILE = 10;
    private static final int REQ_CAMERA = 11;
    private static final int REQ_LOCATION = 12;
    private static final int REQ_STORAGE = 13;
    private static final String START_PATH = "/dashboard";
    /** Pages a home-screen shortcut (res/xml/shortcuts.xml) may open; anything else opens the dashboard. */
    private static final java.util.List<String> SHORTCUT_PATHS = java.util.Arrays.asList("/dashboard", "/leave");
    private static final String BRIDGE = "PCGHRMISApp";

    private Uri base;
    private WebView web;
    private ProgressBar progress;
    private LinearLayout errorView;
    private TextView errorDetail;

    private ValueCallback<Uri[]> fileCallback;
    private PermissionRequest pendingMediaRequest;
    private GeolocationPermissions.Callback pendingGeoCallback;
    private String pendingGeoOrigin;
    private Runnable pendingDownload;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        base = Uri.parse(BuildConfig.BASE_URL);

        int navy = getColor(R.color.navy);
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(navy);

        web = new WebView(this);
        web.setBackgroundColor(Color.WHITE);
        root.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setMax(100);
        progress.setProgressTintList(ColorStateList.valueOf(getColor(R.color.gold)));
        progress.setVisibility(View.GONE);
        root.addView(progress, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(4), Gravity.TOP));

        errorView = buildErrorView();
        root.addView(errorView, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        setContentView(root);
        applySystemBarInsets(root);
        configureWebView();
        registerBackHandler();

        String shortcut = shortcutPath(getIntent());
        if (shortcut != null) {
            web.loadUrl(BuildConfig.BASE_URL + shortcut);
        } else if (savedInstanceState == null || web.restoreState(savedInstanceState) == null) {
            web.loadUrl(BuildConfig.BASE_URL + START_PATH);
        }
    }

    /** The app is already open (singleTask) and a shortcut is tapped: go to its page. */
    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        String shortcut = shortcutPath(intent);
        if (shortcut != null && web != null) {
            errorView.setVisibility(View.GONE);
            web.loadUrl(BuildConfig.BASE_URL + shortcut);
        }
    }

    private static String shortcutPath(Intent intent) {
        if (intent == null) {
            return null;
        }
        String path = intent.getStringExtra("path");
        return path != null && SHORTCUT_PATHS.contains(path) ? path : null;
    }

    // ------------------------------------------------------------------ setup

    @android.annotation.SuppressLint("SetJavaScriptEnabled") // the portal needs JavaScript; only BASE_URL is ever loaded
    private void configureWebView() {
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setMediaPlaybackRequiresUserGesture(false); // live camera preview on the registration page
        s.setGeolocationEnabled(true);
        s.setSupportMultipleWindows(false);
        s.setJavaScriptCanOpenWindowsAutomatically(false);
        s.setSupportZoom(true);
        s.setBuiltInZoomControls(true);
        s.setDisplayZoomControls(false);
        s.setUserAgentString(s.getUserAgentString() + " PCGHRMIS-Android/" + BuildConfig.VERSION_NAME);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            s.setSafeBrowsingEnabled(true);
        }

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(web, false);
        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG);

        web.addJavascriptInterface(new PrintBridge(), BRIDGE);
        web.setWebViewClient(new PortalClient());
        web.setWebChromeClient(new PortalChromeClient());
        web.setDownloadListener(this::onDownload);
    }

    /** Android 15+ draws behind the status and navigation bars: keep the page clear of them and of the keyboard. */
    private void applySystemBarInsets(View root) {
        if (Build.VERSION.SDK_INT >= 35) {
            root.setOnApplyWindowInsetsListener((v, insets) -> {
                Insets i = insets.getInsets(WindowInsets.Type.systemBars() | WindowInsets.Type.ime() | WindowInsets.Type.displayCutout());
                v.setPadding(i.left, i.top, i.right, i.bottom);
                return WindowInsets.CONSUMED;
            });
        } else {
            getWindow().setStatusBarColor(getColor(R.color.navy));
            getWindow().setNavigationBarColor(getColor(R.color.navy));
        }
    }

    private void registerBackHandler() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
                OnBackInvokedDispatcher.PRIORITY_DEFAULT, this::goBackOrLeave);
        }
    }

    private void goBackOrLeave() {
        if (errorView.getVisibility() != View.VISIBLE && web.canGoBack()) {
            web.goBack();
        } else {
            finish();
        }
    }

    @Override
    @SuppressWarnings("deprecation")
    public void onBackPressed() { // Android 12 and older; 13+ use registerBackHandler()
        if (errorView.getVisibility() != View.VISIBLE && web.canGoBack()) {
            web.goBack();
        } else {
            super.onBackPressed();
        }
    }

    private LinearLayout buildErrorView() {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setGravity(Gravity.CENTER);
        box.setBackgroundColor(Color.WHITE);
        int pad = dp(32);
        box.setPadding(pad, pad, pad, pad);
        box.setVisibility(View.GONE);

        TextView title = new TextView(this);
        title.setText(R.string.error_title);
        title.setTextSize(20);
        title.setTextColor(getColor(R.color.navy));
        title.setGravity(Gravity.CENTER);
        box.addView(title);

        errorDetail = new TextView(this);
        errorDetail.setText(R.string.error_message);
        errorDetail.setTextSize(15);
        errorDetail.setTextColor(Color.DKGRAY);
        errorDetail.setGravity(Gravity.CENTER);
        errorDetail.setPadding(0, dp(12), 0, dp(24));
        box.addView(errorDetail);

        Button retry = new Button(this);
        retry.setText(R.string.retry);
        retry.setOnClickListener(v -> {
            errorView.setVisibility(View.GONE);
            String current = web.getUrl();
            if (current == null || !isOwn(Uri.parse(current))) {
                web.loadUrl(BuildConfig.BASE_URL + START_PATH);
            } else {
                web.reload();
            }
        });
        box.addView(retry);
        return box;
    }

    // ---------------------------------------------------------------- helpers

    /** Only the configured PCG HRMIS host over HTTPS (same port) is loaded inside the app. */
    private boolean isOwn(Uri u) {
        return u != null
            && "https".equalsIgnoreCase(u.getScheme())
            && base.getHost() != null
            && base.getHost().equalsIgnoreCase(u.getHost())
            && u.getPort() == base.getPort();
    }

    private void openExternally(Uri u) {
        String scheme = u.getScheme() == null ? "" : u.getScheme().toLowerCase(java.util.Locale.ROOT);
        if (!scheme.equals("https") && !scheme.equals("http") && !scheme.equals("mailto") && !scheme.equals("tel")) {
            return; // intent:, javascript:, file: and other schemes are ignored
        }
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, u));
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, R.string.no_app, Toast.LENGTH_SHORT).show();
        }
    }

    private void showError(CharSequence detail) {
        progress.setVisibility(View.GONE);
        errorDetail.setText(getString(R.string.error_message) + (detail == null ? "" : "\n\n(" + detail + ")"));
        errorView.setVisibility(View.VISIBLE);
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private boolean has(String permission) {
        return checkSelfPermission(permission) == PackageManager.PERMISSION_GRANTED;
    }

    // -------------------------------------------------------------- downloads

    private void onDownload(String url, String userAgent, String contentDisposition, String mimeType, long length) {
        Uri u = Uri.parse(url);
        if (!isOwn(u)) {
            openExternally(u);
            return;
        }
        String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
        Runnable start = () -> {
            DownloadManager.Request r = new DownloadManager.Request(u);
            String cookie = CookieManager.getInstance().getCookie(url);
            if (cookie != null) {
                r.addRequestHeader("Cookie", cookie);
            }
            r.addRequestHeader("User-Agent", userAgent);
            r.setMimeType(mimeType);
            r.setTitle(name);
            r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            DownloadManager dm = getSystemService(DownloadManager.class);
            if (dm != null) {
                dm.enqueue(r);
                Toast.makeText(this, getString(R.string.downloading, name), Toast.LENGTH_SHORT).show();
            }
        };
        if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.P && !has(Manifest.permission.WRITE_EXTERNAL_STORAGE)) {
            pendingDownload = start;
            requestPermissions(new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE}, REQ_STORAGE);
        } else {
            start.run();
        }
    }

    // ----------------------------------------------------------- web clients

    private final class PortalClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri u = request.getUrl();
            if (isOwn(u)) {
                return false;
            }
            openExternally(u);
            return true;
        }

        @Override
        public void onPageStarted(WebView view, String url, Bitmap favicon) {
            errorView.setVisibility(View.GONE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            if (isOwn(Uri.parse(url))) {
                // The portal's Print buttons call window.print(), which WebView does not implement.
                view.evaluateJavascript("window.print=function(){if(window." + BRIDGE + "){" + BRIDGE
                    + ".print(document.title||'PCG HRMIS');}};", null);
            }
            CookieManager.getInstance().flush();
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) {
                showError(error.getDescription());
            }
        }

        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
            handler.cancel(); // never continue over an untrusted certificate
            showError(getString(R.string.error_ssl));
        }

        @Override
        public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
            recreate();
            return true;
        }
    }

    private final class PortalChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            progress.setProgress(newProgress);
            progress.setVisibility(newProgress < 100 ? View.VISIBLE : View.GONE);
        }

        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (fileCallback != null) {
                fileCallback.onReceiveValue(null);
            }
            fileCallback = callback;
            Intent pick = params.createIntent();
            if (params.getMode() == FileChooserParams.MODE_OPEN_MULTIPLE) {
                pick.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
            }
            try {
                startActivityForResult(Intent.createChooser(pick, getString(R.string.choose_file)), REQ_FILE);
                return true;
            } catch (ActivityNotFoundException e) {
                fileCallback = null;
                Toast.makeText(MainActivity.this, R.string.no_app, Toast.LENGTH_SHORT).show();
                return false;
            }
        }

        @Override
        public void onPermissionRequest(PermissionRequest request) {
            boolean wantsCamera = false;
            for (String r : request.getResources()) {
                if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(r)) {
                    wantsCamera = true;
                }
            }
            if (!isOwn(request.getOrigin()) || !wantsCamera) {
                request.deny();
                return;
            }
            if (has(Manifest.permission.CAMERA)) {
                request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
            } else {
                pendingMediaRequest = request;
                requestPermissions(new String[]{Manifest.permission.CAMERA}, REQ_CAMERA);
            }
        }

        @Override
        public void onPermissionRequestCanceled(PermissionRequest request) {
            if (pendingMediaRequest == request) {
                pendingMediaRequest = null;
            }
        }

        @Override
        public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
            if (!isOwn(Uri.parse(origin))) {
                callback.invoke(origin, false, false);
                return;
            }
            if (has(Manifest.permission.ACCESS_FINE_LOCATION) || has(Manifest.permission.ACCESS_COARSE_LOCATION)) {
                callback.invoke(origin, true, false);
            } else {
                pendingGeoOrigin = origin;
                pendingGeoCallback = callback;
                requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, REQ_LOCATION);
            }
        }
    }

    /** Called by the page's window.print() (injected in onPageFinished). */
    final class PrintBridge {
        @JavascriptInterface
        public void print(String title) {
            runOnUiThread(() -> {
                String url = web.getUrl();
                if (url == null || !isOwn(Uri.parse(url))) {
                    return;
                }
                String job = (title == null || title.trim().isEmpty()) ? getString(R.string.app_name) : title.trim();
                PrintManager pm = getSystemService(PrintManager.class);
                if (pm != null) {
                    pm.print(job, web.createPrintDocumentAdapter(job), new PrintAttributes.Builder().build());
                }
            });
        }
    }

    // -------------------------------------------------------------- results

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == REQ_FILE && fileCallback != null) {
            Uri[] result = null;
            if (resultCode == RESULT_OK && data != null) {
                ClipData clip = data.getClipData();
                if (clip != null && clip.getItemCount() > 0) {
                    result = new Uri[clip.getItemCount()];
                    for (int i = 0; i < clip.getItemCount(); i++) {
                        result[i] = clip.getItemAt(i).getUri();
                    }
                } else {
                    result = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
                }
            }
            fileCallback.onReceiveValue(result);
            fileCallback = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        boolean granted = false;
        for (int g : grantResults) {
            if (g == PackageManager.PERMISSION_GRANTED) {
                granted = true;
            }
        }
        if (requestCode == REQ_CAMERA && pendingMediaRequest != null) {
            if (granted) {
                pendingMediaRequest.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
            } else {
                pendingMediaRequest.deny();
            }
            pendingMediaRequest = null;
        } else if (requestCode == REQ_LOCATION && pendingGeoCallback != null) {
            pendingGeoCallback.invoke(pendingGeoOrigin, granted, false);
            pendingGeoCallback = null;
            pendingGeoOrigin = null;
        } else if (requestCode == REQ_STORAGE && pendingDownload != null) {
            if (granted) {
                pendingDownload.run();
            }
            pendingDownload = null;
        }
    }

    // ------------------------------------------------------------- lifecycle

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        web.saveState(outState);
    }

    @Override
    protected void onResume() {
        super.onResume();
        web.onResume();
    }

    @Override
    protected void onPause() {
        web.onPause();
        CookieManager.getInstance().flush();
        super.onPause();
    }

    @Override
    protected void onDestroy() {
        if (web != null) {
            web.removeJavascriptInterface(BRIDGE);
            web.destroy();
        }
        super.onDestroy();
    }
}
