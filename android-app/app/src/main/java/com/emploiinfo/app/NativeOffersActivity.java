package com.emploiinfo.app;

import android.Manifest;
import android.app.NotificationManager;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class NativeOffersActivity extends AppCompatActivity {
    private static final long REFRESH_MS = 30000;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private final ApiClient apiClient = new ApiClient();
    private SwipeRefreshLayout swipe;
    private LinearLayout list;
    private TextView status;
    private volatile boolean streamRunning;
    private Thread streamThread;
    private String pendingOfferId;
    private final Runnable autoRefresh = this::loadOffers;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestNotificationPermission();
        registerFirebaseToken();
        pendingOfferId = getIntent() != null ? getIntent().getStringExtra("offer_id") : null;
        setContentView(createView());
        loadOffers();
        startOfferStream();
    }

    private View createView() {
        swipe = new SwipeRefreshLayout(this);
        swipe.setColorSchemeColors(0xFFC9A227, 0xFF111111);
        swipe.setOnRefreshListener(this::loadOffers);

        ScrollView scroll = new ScrollView(this);
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(24, 24, 24, 24);
        root.setBackgroundColor(0xFFFFFFFF);

        TextView title = new TextView(this);
        title.setText("EMPLOI INFO");
        title.setTextSize(24);
        title.setTextColor(0xFF111111);
        title.setGravity(Gravity.CENTER_VERTICAL);
        title.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        root.addView(title, new LinearLayout.LayoutParams(-1, -2));

        status = new TextView(this);
        status.setText("Chargement des annonces...");
        status.setTextColor(0xFF555555);
        status.setPadding(0, 12, 0, 18);
        root.addView(status, new LinearLayout.LayoutParams(-1, -2));

        list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        root.addView(list, new LinearLayout.LayoutParams(-1, -2));

        scroll.addView(root);
        swipe.addView(scroll);
        return swipe;
    }

    private void loadOffers() {
        handler.removeCallbacks(autoRefresh);
        swipe.setRefreshing(true);
        apiClient.getOffers(100, new ApiClient.Callback() {
            @Override
            public void onSuccess(@NonNull String body) {
                runOnUiThread(() -> renderOffers(body));
            }

            @Override
            public void onError(@NonNull Exception error) {
                runOnUiThread(() -> {
                    swipe.setRefreshing(false);
                    status.setText("API indisponible. Nouvelle tentative automatique...");
                    scheduleRefresh();
                });
            }
        });
    }

    private void renderOffers(String body) {
        list.removeAllViews();
        try {
            JSONObject json = new JSONObject(body);
            JSONArray offers = json.optJSONArray("data");
            int count = offers == null ? 0 : offers.length();
            status.setText(count + " annonce" + (count > 1 ? "s" : "") + " en ligne");
            for (int i = 0; i < count; i++) {
                JSONObject offer = offers.optJSONObject(i);
                if (offer != null) list.addView(offerView(offer));
            }
            openPendingOffer(offers);
        } catch (Exception error) {
            status.setText("Reponse API invalide.");
        } finally {
            swipe.setRefreshing(false);
            scheduleRefresh();
        }
    }

    private void openPendingOffer(JSONArray offers) {
        if (pendingOfferId == null || pendingOfferId.trim().isEmpty() || offers == null) return;
        for (int i = 0; i < offers.length(); i++) {
            JSONObject offer = offers.optJSONObject(i);
            if (offer != null && pendingOfferId.equals(offer.optString("id"))) {
                pendingOfferId = null;
                showOffer(offer);
                return;
            }
        }
        apiClient.getOffer(pendingOfferId, new ApiClient.Callback() {
            @Override
            public void onSuccess(@NonNull String body) {
                try {
                    JSONObject offer = new JSONObject(body).optJSONObject("data");
                    if (offer != null) runOnUiThread(() -> showOffer(offer));
                    pendingOfferId = null;
                } catch (Exception ignored) {
                }
            }

            @Override
            public void onError(@NonNull Exception error) {
            }
        });
    }

    private View offerView(JSONObject offer) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(20, 18, 20, 18);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(0xFFFFFFFF);
        bg.setStroke(1, 0xFFE0E0E0);
        bg.setCornerRadius(8);
        card.setBackground(bg);

        TextView title = new TextView(this);
        title.setText(offer.optString("title", "Annonce"));
        title.setTextColor(0xFF111111);
        title.setTextSize(18);
        title.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        card.addView(title);

        TextView meta = new TextView(this);
        meta.setText((offer.optString("category") + "  " + offer.optString("city")).trim());
        meta.setTextColor(0xFFC09A20);
        meta.setPadding(0, 8, 0, 8);
        card.addView(meta);

        TextView description = new TextView(this);
        description.setText(stripHtml(offer.optString("notice", offer.optString("description", ""))));
        description.setTextColor(0xFF333333);
        description.setMaxLines(4);
        card.addView(description);

        Button details = new Button(this);
        details.setText("Voir details");
        details.setOnClickListener(v -> showOffer(offer));
        card.addView(details);

        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, -2);
        lp.setMargins(0, 0, 0, 16);
        card.setLayoutParams(lp);
        return card;
    }

    private void showOffer(JSONObject offer) {
        new AlertDialog.Builder(this)
            .setTitle(offer.optString("title", "Annonce"))
            .setMessage(stripHtml(offer.optString("description", "")))
            .setPositiveButton("Fermer", null)
            .show();
    }

    private String stripHtml(String text) {
        return text == null ? "" : text.replaceAll("<[^>]*>", " ").replaceAll("\\s+", " ").trim();
    }

    private void scheduleRefresh() {
        handler.removeCallbacks(autoRefresh);
        handler.postDelayed(autoRefresh, REFRESH_MS);
    }

    private void startOfferStream() {
        if (streamRunning) return;
        streamRunning = true;
        streamThread = new Thread(() -> {
            while (streamRunning) {
                HttpURLConnection conn = null;
                try {
                    URL url = new URL(BuildConfig.API_BASE_URL + "/offers/stream");
                    conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestProperty("Accept", "text/event-stream");
                    conn.setConnectTimeout(8000);
                    conn.setReadTimeout(0);
                    if (conn.getResponseCode() >= 200 && conn.getResponseCode() < 300) {
                        try (BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream(), "UTF-8"))) {
                            String line;
                            while (streamRunning && (line = reader.readLine()) != null) {
                                if (line.startsWith("event: offer")) runOnUiThread(this::loadOffers);
                            }
                        }
                    }
                } catch (Exception ignored) {
                    try {
                        Thread.sleep(5000);
                    } catch (InterruptedException interrupted) {
                        Thread.currentThread().interrupt();
                    }
                } finally {
                    if (conn != null) conn.disconnect();
                }
            }
        }, "native-offers-stream");
        streamThread.setDaemon(true);
        streamThread.start();
    }

    private void registerFirebaseToken() {
        try {
            FirebaseMessaging.getInstance().getToken().addOnSuccessListener(token -> new Thread(() -> {
                HttpURLConnection conn = null;
                try {
                    URL url = new URL(BuildConfig.REGISTER_TOKEN_URL);
                    conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestMethod("POST");
                    conn.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                    conn.setDoOutput(true);
                    String body = "{\"token\":\"" + token.replace("\\", "\\\\").replace("\"", "\\\"") + "\",\"platform\":\"android\",\"app\":\"" + BuildConfig.APP_DISPLAY_NAME + "\"}";
                    try (OutputStream os = conn.getOutputStream()) {
                        os.write(body.getBytes("UTF-8"));
                    }
                    conn.getResponseCode();
                } catch (Exception ignored) {
                } finally {
                    if (conn != null) conn.disconnect();
                }
            }).start());
        } catch (Exception ignored) {
        }
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1001);
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacks(autoRefresh);
        streamRunning = false;
        if (streamThread != null) streamThread.interrupt();
        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (manager != null) manager.cancel(EmploiFirebaseMessagingService.SUMMARY_NOTIFICATION_ID);
        super.onDestroy();
    }
}
