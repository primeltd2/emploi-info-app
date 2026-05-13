package com.emploiinfo.app;

import android.Manifest;
import android.annotation.TargetApi;
import android.app.AlertDialog;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.drawable.GradientDrawable;
import android.media.AudioAttributes;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import androidx.appcompat.app.AppCompatActivity;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;

import com.google.android.gms.ads.AdListener;
import com.google.android.gms.ads.AdLoader;
import com.google.android.gms.ads.AdError;
import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.AdSize;
import com.google.android.gms.ads.AdView;
import com.google.android.gms.ads.FullScreenContentCallback;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.RequestConfiguration;
import com.google.android.gms.ads.appopen.AppOpenAd;

import java.util.Arrays;
import com.google.android.gms.ads.interstitial.InterstitialAd;
import com.google.android.gms.ads.interstitial.InterstitialAdLoadCallback;
import com.google.android.gms.ads.nativead.NativeAd;
import com.google.android.gms.ads.nativead.NativeAdView;
import com.google.firebase.messaging.FirebaseMessaging;

import java.io.OutputStream;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends AppCompatActivity {
    static final String HOME_URL = BuildConfig.HOME_URL;
    static final String REGISTER_TOKEN_URL = BuildConfig.REGISTER_TOKEN_URL;
    static final String APP_VERSION_URL = BuildConfig.APP_VERSION_URL;
    static final String CHANNEL_ID = "emploi_info_alerts";
    // Unités de production AdMob pour la monétisation
    static final String BANNER_AD_UNIT_ID = "ca-app-pub-7474388862913519/2132851690";
    static final String INTERSTITIAL_AD_UNIT_ID = "ca-app-pub-7474388862913519/2816437463";
    static final String NATIVE_AD_UNIT_ID = "ca-app-pub-7474388862913519/7022216295";
    static final String APP_OPEN_AD_UNIT_ID = "ca-app-pub-7474388862913519/7022216295";
    static final long IDLE_AD_DELAY_MS = 60000;
    static final long MIN_AD_INTERVAL_MS = 300000;
    static final long MIN_APP_OPEN_INTERVAL_MS = 14400000;

    private static final int REQUEST_WEBVIEW_PERMISSIONS = 1003;

    private WebView webView;
    private ValueCallback<Uri[]> filePathCallback;
    private ActivityResultLauncher<String> filePickerLauncher;
    private PermissionRequest pendingWebViewPermissionRequest;
    private final Handler idleHandler = new Handler(Looper.getMainLooper());
    private SwipeRefreshLayout swipeRefreshLayout;
    private FrameLayout webContainer;
    private LinearLayout loadingOverlay;
    private TextView loadingText;
    private Button reloadButton;
    private FrameLayout nativeAdContainer;
    private AdView bannerAdView;
    private InterstitialAd interstitialAd;
    private AppOpenAd appOpenAd;
    private NativeAd nativeAd;
    private String mobileUserAgent;
    private String desktopUserAgent;
    private boolean desktopMeetingMode;
    private boolean isInterstitialLoading;
    private boolean isAppOpenLoading;
    private boolean isShowingFullScreenAd;
    private boolean isActivityResumed;
    private boolean updateDialogShowing;
    private boolean pageHadError;
    private long lastInterstitialShownAt;
    private long lastAppOpenShownAt;
    private final Runnable idleAdRunnable = this::showInterstitialAfterIdle;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        try {
            createNotificationChannel();
            requestNotificationPermission();
        } catch (Exception ignored) {
            // Startup must stay usable even if notification services are unavailable on a device.
        }
        
        filePickerLauncher = registerForActivityResult(
            new ActivityResultContracts.GetMultipleContents(),
            result -> {
                if (filePathCallback != null) {
                    Uri[] results = result.toArray(new Uri[0]);
                    filePathCallback.onReceiveValue(results.length > 0 ? results : null);
                    filePathCallback = null;
                }
            }
        );
        
        setContentView(createContentView());

        try {
            if (BuildConfig.ENABLE_ADS) {
                MobileAds.setRequestConfiguration(
                    new RequestConfiguration.Builder()
                        .build()
                );

                MobileAds.initialize(this, initializationStatus -> {
                    loadInterstitialAd();
                    loadNativeAd();
                    loadBannerAd();
                });
            }
        } catch (Exception ignored) {
            // Ad SDK failures should never close the application.
        }

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
            CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);
        }
        CookieManager.getInstance().setAcceptCookie(true);
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        webView.addJavascriptInterface(new AndroidAppBridge(), "EmploiInfoAndroid");
        mobileUserAgent = settings.getUserAgentString() + " " + BuildConfig.USER_AGENT_SUFFIX;
        desktopUserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 " + BuildConfig.USER_AGENT_SUFFIX;
        settings.setUserAgentString(mobileUserAgent);

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, WebChromeClient.FileChooserParams fileChooserParams) {
                if (MainActivity.this.filePathCallback != null) {
                    MainActivity.this.filePathCallback.onReceiveValue(null);
                }
                MainActivity.this.filePathCallback = filePathCallback;
                try {
                    String[] acceptTypes = fileChooserParams.getAcceptTypes();
                    String mimeType = resolveFileChooserMimeType(acceptTypes);
                    filePickerLauncher.launch(mimeType);
                } catch (Exception e) {
                    MainActivity.this.filePathCallback = null;
                    return false;
                }
                return true;
            }

            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                runOnUiThread(() -> {
                    if (canGrantWebViewRequest(request)) {
                        request.grant(filterGrantableWebViewResources(request));
                    } else {
                        pendingWebViewPermissionRequest = request;
                        requestWebViewPermissionsFor(request);
                    }
                });
            }

            @Override
            public void onPermissionRequestCanceled(PermissionRequest request) {
                if (pendingWebViewPermissionRequest == request) {
                    pendingWebViewPermissionRequest = null;
                }
            }
        });

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleExternalAppUrl(view, url);
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, android.webkit.WebResourceRequest request) {
                return request != null && request.getUrl() != null && handleExternalAppUrl(view, request.getUrl().toString());
            }

            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                if (shouldReturnToPanelAfterMeeting(url)) {
                    applyMeetingDisplayMode(HOME_URL);
                    view.loadUrl(HOME_URL);
                    return;
                }
                applyMeetingDisplayMode(url);
                pageHadError = false;
                showLoading(false);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                if (!pageHadError) hideLoading();
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                resetIdleAdTimer();
            }

            @Override
            public void onReceivedError(WebView view, android.webkit.WebResourceRequest request, android.webkit.WebResourceError error) {
                super.onReceivedError(view, request, error);
                if (Build.VERSION.SDK_INT >= 21 && !request.isForMainFrame()) return;
                pageHadError = true;
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                showLoading(true);
                idleHandler.postDelayed(() -> {
                    if (pageHadError && webView != null) webView.reload();
                }, 7000);
            }
        });
        String startUrl = resolveStartUrl(getIntent());
        applyMeetingDisplayMode(startUrl);
        webView.loadUrl(startUrl);

        requestWebViewPermissions();
        try {
            FirebaseMessaging.getInstance().getToken().addOnSuccessListener(this::registerToken);
        } catch (Exception ignored) {
        }
        checkForAppUpdate(false);
    }

    private String resolveFileChooserMimeType(String[] acceptTypes) {
        if (acceptTypes == null || acceptTypes.length == 0) return "*/*";
        for (String type : acceptTypes) {
            if (type != null && !type.trim().isEmpty() && !"*/*".equals(type.trim())) return type.trim();
        }
        return "*/*";
    }

    private boolean hasPermission(String permission) {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M ||
            ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED;
    }

    private boolean hasWebViewPermissions() {
        return hasPermission(Manifest.permission.RECORD_AUDIO) && hasPermission(Manifest.permission.CAMERA);
    }

    private boolean canGrantWebViewRequest(PermissionRequest request) {
        for (String resource : request.getResources()) {
            if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource) && !hasPermission(Manifest.permission.RECORD_AUDIO)) return false;
            if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource) && !hasPermission(Manifest.permission.CAMERA)) return false;
        }
        return true;
    }

    private String[] filterGrantableWebViewResources(PermissionRequest request) {
        List<String> resources = new ArrayList<>();
        for (String resource : request.getResources()) {
            if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource) && hasPermission(Manifest.permission.RECORD_AUDIO)) {
                resources.add(resource);
            } else if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource) && hasPermission(Manifest.permission.CAMERA)) {
                resources.add(resource);
            }
        }
        return resources.toArray(new String[0]);
    }

    private void requestWebViewPermissions() {
        requestWebViewPermissionsFor(null);
    }

    private void requestWebViewPermissionsFor(@Nullable PermissionRequest request) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return;
        List<String> permissions = new ArrayList<>();
        if (request == null) {
            addPermissionIfMissing(permissions, Manifest.permission.RECORD_AUDIO);
            addPermissionIfMissing(permissions, Manifest.permission.CAMERA);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                addPermissionIfMissing(permissions, Manifest.permission.BLUETOOTH_CONNECT);
            }
        } else {
            for (String resource : request.getResources()) {
                if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource) && !hasPermission(Manifest.permission.RECORD_AUDIO)) {
                    addPermissionIfMissing(permissions, Manifest.permission.RECORD_AUDIO);
                }
                if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource) && !hasPermission(Manifest.permission.CAMERA)) {
                    addPermissionIfMissing(permissions, Manifest.permission.CAMERA);
                }
            }
        }
        if (!permissions.isEmpty()) {
            ActivityCompat.requestPermissions(this, permissions.toArray(new String[0]), REQUEST_WEBVIEW_PERMISSIONS);
        }
    }

    private void addPermissionIfMissing(List<String> permissions, String permission) {
        if (!permissions.contains(permission)) permissions.add(permission);
    }

@Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == REQUEST_WEBVIEW_PERMISSIONS) {
            if (pendingWebViewPermissionRequest != null) {
                PermissionRequest request = pendingWebViewPermissionRequest;
                pendingWebViewPermissionRequest = null;
                if (canGrantWebViewRequest(request)) {
                    request.grant(filterGrantableWebViewResources(request));
                } else {
                    request.deny();
                }
            }
        }
    }

    private View createContentView() {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(0xFFFFFFFF);

        webContainer = new FrameLayout(this);
        swipeRefreshLayout = new SwipeRefreshLayout(this);
        swipeRefreshLayout.setColorSchemeColors(0xFFC9A227, 0xFFFFD84A, 0xFF111111);
        swipeRefreshLayout.setOnRefreshListener(() -> {
            pageHadError = false;
            showLoading(false);
            webView.reload();
        });

        webView = new WebView(this);
        swipeRefreshLayout.addView(
            webView,
            new SwipeRefreshLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        );
        webContainer.addView(
            swipeRefreshLayout,
            new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        );
        loadingOverlay = createLoadingOverlay();
        webContainer.addView(
            loadingOverlay,
            new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        );
        root.addView(
            webContainer,
            new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                0,
                1
            )
        );

        nativeAdContainer = new FrameLayout(this);
        nativeAdContainer.setVisibility(View.GONE);
        root.addView(
            nativeAdContainer,
            new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            )
        );

        if (BuildConfig.ENABLE_ADS) {
            try {
                bannerAdView = new AdView(this);
                bannerAdView.setAdUnitId(BANNER_AD_UNIT_ID);
                bannerAdView.setAdSize(AdSize.BANNER);
                root.addView(
                    bannerAdView,
                    new LinearLayout.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.WRAP_CONTENT
                    )
                );
            } catch (Exception ignored) {
                bannerAdView = null;
            }
        }

        return root;
    }

    private LinearLayout createLoadingOverlay() {
        LinearLayout overlay = new LinearLayout(this);
        overlay.setOrientation(LinearLayout.VERTICAL);
        overlay.setGravity(Gravity.CENTER);
        overlay.setPadding(32, 32, 32, 32);
        overlay.setBackgroundColor(0xEE111111);

        ProgressBar progress = new ProgressBar(this);
        progress.setIndeterminate(true);
        overlay.addView(
            progress,
            new LinearLayout.LayoutParams(72, 72)
        );

        loadingText = new TextView(this);
        loadingText.setText("Prime Ltd");
        loadingText.setTextColor(0xFFFFD84A);
        loadingText.setTextSize(18);
        loadingText.setTypeface(null, android.graphics.Typeface.BOLD);
        loadingText.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams textParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        textParams.setMargins(0, 14, 0, 0);
        overlay.addView(loadingText, textParams);

        reloadButton = new Button(this);
        reloadButton.setText("Actualiser");
        reloadButton.setTextColor(0xFF111111);
        GradientDrawable bg = new GradientDrawable();
        bg.setColor(0xFFFFD84A);
        bg.setCornerRadius(6);
        reloadButton.setBackground(bg);
        reloadButton.setVisibility(View.GONE);
        reloadButton.setOnClickListener(v -> {
            pageHadError = false;
            showLoading(false);
            webView.reload();
        });
        LinearLayout.LayoutParams buttonParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        buttonParams.setMargins(0, 16, 0, 0);
        overlay.addView(reloadButton, buttonParams);

        return overlay;
    }

    private void showLoading(boolean failed) {
        if (loadingOverlay == null) return;
        loadingOverlay.setVisibility(View.VISIBLE);
        if (loadingText != null) {
            loadingText.setText(failed ? "Prime Ltd" : "Prime Ltd");
        }
        if (reloadButton != null) {
            reloadButton.setVisibility(failed ? View.VISIBLE : View.GONE);
        }
    }

    private void hideLoading() {
        if (loadingOverlay != null) loadingOverlay.setVisibility(View.GONE);
        if (reloadButton != null) reloadButton.setVisibility(View.GONE);
    }

    private void loadBannerAd() {
        try {
            if (bannerAdView != null) {
                bannerAdView.loadAd(new AdRequest.Builder().build());
            }
        } catch (Exception ignored) {
        }
    }

    private void loadNativeAd() {
        try {
            new AdLoader.Builder(this, NATIVE_AD_UNIT_ID)
                .forNativeAd(ad -> {
                    if (nativeAd != null) nativeAd.destroy();
                    nativeAd = ad;
                    showNativeAd(ad);
                })
                .withAdListener(new AdListener() {
                    @Override
                    public void onAdFailedToLoad(@NonNull LoadAdError loadAdError) {
                        if (nativeAdContainer != null) nativeAdContainer.setVisibility(View.GONE);
                    }
                })
                .build()
                .loadAd(new AdRequest.Builder().build());
        } catch (Exception ignored) {
            if (nativeAdContainer != null) nativeAdContainer.setVisibility(View.GONE);
        }
    }

    private void showNativeAd(@NonNull NativeAd ad) {
        if (nativeAdContainer == null) return;

        NativeAdView adView = new NativeAdView(this);
        LinearLayout body = new LinearLayout(this);
        body.setOrientation(LinearLayout.VERTICAL);
        body.setPadding(24, 16, 24, 16);
        body.setBackgroundColor(0xFFFFFFFF);

        TextView badge = new TextView(this);
        badge.setText("Annonce");
        badge.setTextColor(0xFF666666);
        badge.setTextSize(12);
        body.addView(badge);

        TextView headline = new TextView(this);
        headline.setText(ad.getHeadline());
        headline.setTextColor(0xFF111111);
        headline.setTextSize(16);
        headline.setTypeface(null, android.graphics.Typeface.BOLD);
        body.addView(headline);
        adView.setHeadlineView(headline);

        TextView advertiser = new TextView(this);
        advertiser.setTextColor(0xFF555555);
        advertiser.setTextSize(13);
        if (ad.getAdvertiser() != null) {
            advertiser.setText(ad.getAdvertiser());
            body.addView(advertiser);
            adView.setAdvertiserView(advertiser);
        }

        TextView text = new TextView(this);
        text.setTextColor(0xFF333333);
        text.setTextSize(14);
        if (ad.getBody() != null) {
            text.setText(ad.getBody());
            body.addView(text);
            adView.setBodyView(text);
        }

        Button callToAction = new Button(this);
        callToAction.setGravity(Gravity.CENTER);
        if (ad.getCallToAction() != null) {
            callToAction.setText(ad.getCallToAction());
            body.addView(callToAction);
            adView.setCallToActionView(callToAction);
        }

        adView.addView(
            body,
            new NativeAdView.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            )
        );
        adView.setNativeAd(ad);

        nativeAdContainer.removeAllViews();
        nativeAdContainer.addView(
            adView,
            new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            )
        );
        nativeAdContainer.setVisibility(View.VISIBLE);
    }

    private void loadInterstitialAd() {
        if (isInterstitialLoading || interstitialAd != null) return;
        isInterstitialLoading = true;

        try {
            InterstitialAd.load(
                this,
                INTERSTITIAL_AD_UNIT_ID,
                new AdRequest.Builder().build(),
                new InterstitialAdLoadCallback() {
                    @Override
                    public void onAdLoaded(@NonNull InterstitialAd ad) {
                        interstitialAd = ad;
                        isInterstitialLoading = false;
                        interstitialAd.setFullScreenContentCallback(new FullScreenContentCallback() {
                            @Override
                            public void onAdDismissedFullScreenContent() {
                                isShowingFullScreenAd = false;
                                interstitialAd = null;
                                lastInterstitialShownAt = System.currentTimeMillis();
                                loadInterstitialAd();
                                resetIdleAdTimer();
                            }

                            @Override
                            public void onAdFailedToShowFullScreenContent(@NonNull AdError adError) {
                                isShowingFullScreenAd = false;
                                interstitialAd = null;
                                loadInterstitialAd();
                                resetIdleAdTimer();
                            }
                        });
                    }

                    @Override
                    public void onAdFailedToLoad(@NonNull LoadAdError loadAdError) {
                        interstitialAd = null;
                        isInterstitialLoading = false;
                    }
                }
            );
        } catch (Exception ignored) {
            interstitialAd = null;
            isInterstitialLoading = false;
        }
    }

    private void resetIdleAdTimer() {
        idleHandler.removeCallbacks(idleAdRunnable);
        if (!BuildConfig.ENABLE_ADS) return;
        if (isActivityResumed) {
            idleHandler.postDelayed(idleAdRunnable, IDLE_AD_DELAY_MS);
        }
    }

    private void showInterstitialAfterIdle() {
        if (!BuildConfig.ENABLE_ADS) return;
        if (!isActivityResumed || isShowingFullScreenAd) return;

        long now = System.currentTimeMillis();
        if (now - lastInterstitialShownAt < MIN_AD_INTERVAL_MS) {
            resetIdleAdTimer();
            return;
        }

        if (interstitialAd == null) {
            loadInterstitialAd();
            resetIdleAdTimer();
            return;
        }

        isShowingFullScreenAd = true;
        interstitialAd.show(this);
    }

    private void loadAppOpenAd() {
        if (!BuildConfig.ENABLE_ADS) return;
        if (isAppOpenLoading || appOpenAd != null) return;
        isAppOpenLoading = true;

        AppOpenAd.load(
            this,
            APP_OPEN_AD_UNIT_ID,
            new AdRequest.Builder().build(),
            new AppOpenAd.AppOpenAdLoadCallback() {
                @Override
                public void onAdLoaded(@NonNull AppOpenAd ad) {
                    appOpenAd = ad;
                    isAppOpenLoading = false;
                    appOpenAd.setFullScreenContentCallback(new FullScreenContentCallback() {
                        @Override
                        public void onAdDismissedFullScreenContent() {
                            isShowingFullScreenAd = false;
                            appOpenAd = null;
                            lastAppOpenShownAt = System.currentTimeMillis();
                            loadAppOpenAd();
                        }

                        @Override
                        public void onAdFailedToShowFullScreenContent(@NonNull AdError adError) {
                            isShowingFullScreenAd = false;
                            appOpenAd = null;
                            loadAppOpenAd();
                        }
                    });
                    showAppOpenAdIfAvailable();
                }

                @Override
                public void onAdFailedToLoad(@NonNull LoadAdError loadAdError) {
                    appOpenAd = null;
                    isAppOpenLoading = false;
                }
            }
        );
    }

    private void showAppOpenAdIfAvailable() {
        if (!BuildConfig.ENABLE_ADS) return;
        if (!isActivityResumed || isShowingFullScreenAd) return;

        long now = System.currentTimeMillis();
        if (now - lastAppOpenShownAt < MIN_APP_OPEN_INTERVAL_MS) return;

        if (appOpenAd == null) {
            loadAppOpenAd();
            return;
        }

        isShowingFullScreenAd = true;
        appOpenAd.show(this);
    }

    @Override
    public boolean dispatchTouchEvent(MotionEvent ev) {
        resetIdleAdTimer();
        return super.dispatchTouchEvent(ev);
    }

    @Override
    public void onUserInteraction() {
        super.onUserInteraction();
        resetIdleAdTimer();
    }

    @Override
    protected void onResume() {
        super.onResume();
        isActivityResumed = true;
        try {
            FirebaseMessaging.getInstance().getToken().addOnSuccessListener(this::registerToken);
        } catch (Exception ignored) {
        }
        checkForAppUpdate(false);
        resetIdleAdTimer();
    }

    @Override
    protected void onPause() {
        idleHandler.removeCallbacks(idleAdRunnable);
        isActivityResumed = false;
        super.onPause();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        if (webView != null) {
            String startUrl = resolveStartUrl(intent);
            applyMeetingDisplayMode(startUrl);
            webView.loadUrl(startUrl);
        }
    }

    @Override
    protected void onDestroy() {
        idleHandler.removeCallbacks(idleAdRunnable);
        if (bannerAdView != null) bannerAdView.destroy();
        if (nativeAd != null) nativeAd.destroy();
        super.onDestroy();
    }

    private String resolveStartUrl(Intent intent) {
        String url = intent != null ? intent.getStringExtra("url") : null;
        Uri data = intent != null ? intent.getData() : null;
        if (data != null) {
            if (BuildConfig.APP_SCHEME.equals(data.getScheme())) {
                String target = data.getQueryParameter("url");
                if (target != null && target.startsWith("https://emploi-info.page.gd/")) return target;
            }
            if ("https".equals(data.getScheme()) && "emploi-info.page.gd".equals(data.getHost())) {
                return data.toString();
            }
        }
        return (url != null && url.startsWith("https://emploi-info.page.gd/")) ? url : HOME_URL;
    }

    private boolean isAdminPanelUrl(String url) {
        try {
            Uri uri = Uri.parse(url);
            String host = uri.getHost();
            String path = uri.getPath();
            return "emploi-info.page.gd".equals(host) && path != null && path.endsWith("/admin.html");
        } catch (Exception ignored) {
            return false;
        }
    }

    private boolean isMeetingDesktopAllowedUrl(String url) {
        try {
            Uri uri = Uri.parse(url);
            String host = uri.getHost();
            String path = uri.getPath();
            return ("emploi-info.page.gd".equals(host)
                    && path != null
                    && path.endsWith("/admin_call.php")
                    && "1".equals(uri.getQueryParameter("host")))
                || "meet.jit.si".equals(host)
                || "accounts.google.com".equals(host)
                || (host != null && host.endsWith(".google.com"))
                || "github.com".equals(host);
        } catch (Exception ignored) {
            return false;
        }
    }

    private boolean shouldReturnToPanelAfterMeeting(String url) {
        return desktopMeetingMode && !isMeetingDesktopAllowedUrl(url) && !isAdminPanelUrl(url);
    }

    private void applyMeetingDisplayMode(String url) {
        if (webView == null || mobileUserAgent == null || desktopUserAgent == null) return;
        boolean hostMeeting = isMeetingDesktopAllowedUrl(url);
        if (desktopMeetingMode == hostMeeting) return;
        desktopMeetingMode = hostMeeting;
        WebSettings settings = webView.getSettings();
        settings.setUserAgentString(hostMeeting ? desktopUserAgent : mobileUserAgent);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(hostMeeting);
    }

    private boolean handleExternalAppUrl(WebView view, String url) {
        if (url == null) return false;
        try {
            if (url.startsWith("intent://")) {
                Intent intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
                try {
                    startActivity(intent);
                } catch (ActivityNotFoundException e) {
                    String fallbackUrl = intent.getStringExtra("browser_fallback_url");
                    if (fallbackUrl != null && fallbackUrl.startsWith("https://emploi-info.page.gd/")) {
                        view.loadUrl(fallbackUrl);
                    }
                }
                return true;
            }

            Uri uri = Uri.parse(url);
            String scheme = uri.getScheme();
            if ("emploiinfo".equals(scheme) || "adminemploiinfo".equals(scheme)) {
                startActivity(new Intent(Intent.ACTION_VIEW, uri));
                return true;
            }
        } catch (Exception ignored) {
            return true;
        }
        return false;
    }

    private int currentVersionCode() {
        try {
            if (Build.VERSION.SDK_INT >= 28) {
                return (int) getPackageManager().getPackageInfo(getPackageName(), 0).getLongVersionCode();
            }
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionCode;
        } catch (Exception e) {
            return 1;
        }
    }

    private void checkForAppUpdate(boolean manual) {
        if (updateDialogShowing) return;
        new Thread(() -> {
            HttpURLConnection conn = null;
            try {
                URL url = new URL(APP_VERSION_URL + "?t=" + System.currentTimeMillis());
                conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(7000);
                conn.setReadTimeout(7000);
                conn.setRequestProperty("Cache-Control", "no-cache");
                StringBuilder body = new StringBuilder();
                try (BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream(), "UTF-8"))) {
                    String line;
                    while ((line = reader.readLine()) != null) body.append(line);
                }
                JSONObject json = new JSONObject(body.toString());
                int latest = json.optInt("latest_version_code", 0);
                int minRequired = json.optInt("min_required_version_code", 0);
                int current = currentVersionCode();
                if (latest <= current && minRequired <= current) return;

                String versionName = json.optString("latest_version_name", "");
                String apkUrl = json.optString("apk_url", BuildConfig.APK_DOWNLOAD_URL);
                String message = json.optString("message", "Une nouvelle version est disponible. Veuillez mettre à jour pour continuer.");
                boolean serverForce = json.optBoolean("force_update", false);
                runOnUiThread(() -> showUpdateDialog(latest, versionName, apkUrl, message, serverForce, manual));
            } catch (Exception ignored) {
            } finally {
                if (conn != null) conn.disconnect();
            }
        }).start();
    }

    private void showUpdateDialog(int latestVersionCode, String versionName, String apkUrl, String message, boolean serverForce, boolean manual) {
        if (updateDialogShowing) return;

        SharedPreferences prefs = getSharedPreferences("emploi_info_update", MODE_PRIVATE);
        int savedVersion = prefs.getInt("latest_version_code", 0);
        int reminderCount = savedVersion == latestVersionCode ? prefs.getInt("reminder_count", 0) : 0;
        long lastPrompt = savedVersion == latestVersionCode ? prefs.getLong("last_prompt_at", 0L) : 0L;
        long now = System.currentTimeMillis();
        int nextCount = serverForce ? reminderCount : Math.min(3, reminderCount + 1);
        boolean force = serverForce || nextCount >= 3;

        if (!force && !manual && lastPrompt > 0 && now - lastPrompt < 24L * 60L * 60L * 1000L) {
            return;
        }

        prefs.edit()
            .putInt("latest_version_code", latestVersionCode)
            .putInt("reminder_count", nextCount)
            .putLong("last_prompt_at", now)
            .apply();

        updateDialogShowing = true;
        String title = "Nouvelle version disponible";
        String finalMessage = message + (versionName == null || versionName.isEmpty() ? "" : "\n\nVersion : " + versionName)
            + "\n\nPour renforcer la sécurité de l'application, profiter des dernières corrections et garder une expérience plus fluide, installez la nouvelle APK."
            + (force ? "\n\nCette mise à jour est maintenant obligatoire pour continuer." : "\n\nVous pouvez choisir Plus tard, mais après trois rappels espacés de 24h, la mise à jour deviendra obligatoire.");

        AlertDialog dialog = new AlertDialog.Builder(this)
            .setTitle(title)
            .setMessage(finalMessage)
            .setPositiveButton("Mettre à jour", (d, which) -> {
                updateDialogShowing = false;
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(apkUrl));
                startActivity(intent);
                if (force) finish();
            })
            .setOnDismissListener(d -> updateDialogShowing = false)
            .create();

        if (!force) {
            dialog.setButton(AlertDialog.BUTTON_NEGATIVE, "Plus tard", (d, which) -> {
                updateDialogShowing = false;
                d.dismiss();
            });
        } else {
            dialog.setCancelable(false);
            dialog.setCanceledOnTouchOutside(false);
        }

        dialog.show();
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1001);
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT < 26) return;

        Uri soundUri = Uri.parse("android.resource://" + getPackageName() + "/raw/emploi_info_notification");
        AudioAttributes attrs = new AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build();

        NotificationChannel channel = new NotificationChannel(
            CHANNEL_ID,
            "Alertes EMPLOI INFO",
            NotificationManager.IMPORTANCE_HIGH
        );
        channel.setDescription("Notifications des nouvelles offres EMPLOI INFO");
        channel.enableVibration(true);
        channel.setSound(soundUri, attrs);

        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) manager.createNotificationChannel(channel);
    }

    private void registerToken(String token) {
        new Thread(() -> {
            try {
                URL url = new URL(REGISTER_TOKEN_URL);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                conn.setDoOutput(true);
                String body = "{\"token\":\"" + token.replace("\\", "\\\\").replace("\"", "\\\"") + "\"}";
                try (OutputStream os = conn.getOutputStream()) {
                    os.write(body.getBytes("UTF-8"));
                }
                conn.getResponseCode();
                conn.disconnect();
            } catch (Exception ignored) {
            }
        }).start();
    }

    private class AndroidAppBridge {
        @JavascriptInterface
        public void setNotificationBadge(int count) {
            int safeCount = Math.max(0, count);
            SharedPreferences prefs = getSharedPreferences("emploi_info_notifications", MODE_PRIVATE);
            prefs.edit().putInt("unread_count", safeCount).apply();
            if (safeCount == 0) {
                NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
                if (manager != null) manager.cancel(EmploiFirebaseMessagingService.SUMMARY_NOTIFICATION_ID);
            }
        }
    }
}
