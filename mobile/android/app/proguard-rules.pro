# The print bridge is called from the page's JavaScript by name.
-keepclassmembers class ph.pcg.mepcom.hrmis.MainActivity$PrintBridge {
    @android.webkit.JavascriptInterface <methods>;
}
