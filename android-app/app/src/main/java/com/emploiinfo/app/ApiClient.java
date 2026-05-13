package com.emploiinfo.app;

import androidx.annotation.NonNull;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;

public class ApiClient {
    public interface Callback {
        void onSuccess(@NonNull String body);
        void onError(@NonNull Exception error);
    }

    public void getOffers(int limit, @NonNull Callback callback) {
        int safeLimit = Math.max(1, Math.min(100, limit));
        get("/offers?limit=" + safeLimit, callback);
    }

    public void getOffer(@NonNull String id, @NonNull Callback callback) {
        try {
            get("/offers/" + URLEncoder.encode(id, "UTF-8"), callback);
        } catch (Exception error) {
            callback.onError(error);
        }
    }

    public void getCatalog(@NonNull Callback callback) {
        get("/catalog", callback);
    }

    private void get(@NonNull String path, @NonNull Callback callback) {
        new Thread(() -> {
            HttpURLConnection conn = null;
            try {
                URL url = new URL(BuildConfig.API_BASE_URL + path);
                conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setConnectTimeout(8000);
                conn.setReadTimeout(8000);
                conn.setRequestProperty("Accept", "application/json");

                int status = conn.getResponseCode();
                BufferedReader reader = new BufferedReader(new InputStreamReader(
                    status >= 200 && status < 300 ? conn.getInputStream() : conn.getErrorStream(),
                    "UTF-8"
                ));
                StringBuilder body = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) body.append(line);
                reader.close();

                if (status >= 200 && status < 300) {
                    callback.onSuccess(body.toString());
                } else {
                    callback.onError(new IllegalStateException("API error " + status + ": " + body));
                }
            } catch (Exception error) {
                callback.onError(error);
            } finally {
                if (conn != null) conn.disconnect();
            }
        }).start();
    }
}
