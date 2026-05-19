package com.emploiinfo.app;

import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

import androidx.core.app.NotificationCompat;
import androidx.core.app.RemoteInput;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class NotificationActionReceiver extends BroadcastReceiver {
    public static final String ACTION_COMMENT = "com.emploiinfo.app.ACTION_COMMENT";
    public static final String KEY_COMMENT_TEXT = "emploi_info_comment_text";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (!ACTION_COMMENT.equals(intent.getAction())) return;

        android.os.Bundle results = RemoteInput.getResultsFromIntent(intent);
        CharSequence reply = results != null ? results.getCharSequence(KEY_COMMENT_TEXT) : null;
        String comment = reply == null ? "" : reply.toString().trim();
        String offerId = intent.getStringExtra("offer_id");
        int notificationId = intent.getIntExtra("notification_id", 0);

        if (comment.isEmpty() || offerId == null || offerId.trim().isEmpty()) {
            showStatus(context, notificationId, "Commentaire non envoye", "Le commentaire est vide.");
            return;
        }

        PendingResult pendingResult = goAsync();
        new Thread(() -> {
            boolean ok = postComment(context, offerId, comment);
            showStatus(
                context,
                notificationId,
                ok ? "Commentaire envoye" : "Commentaire non envoye",
                ok ? "Votre commentaire a ete publie." : "Verifiez internet puis reessayez."
            );
            pendingResult.finish();
        }).start();
    }

    private boolean postComment(Context context, String offerId, String comment) {
        HttpURLConnection conn = null;
        try {
            URL endpoint = new URL(MainActivity.API_BASE_URL + "/interactions/comment");
            conn = (HttpURLConnection) endpoint.openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(12000);
            conn.setReadTimeout(12000);
            conn.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            conn.setDoOutput(true);

            JSONObject payload = new JSONObject();
            payload.put("item_type", "annonce");
            payload.put("item_id", offerId);
            payload.put("text", comment);
            payload.put("visitor_id", "android-" + android.provider.Settings.Secure.getString(
                context.getContentResolver(),
                android.provider.Settings.Secure.ANDROID_ID
            ));
            payload.put("username", "Utilisateur Android");

            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.toString().getBytes("UTF-8"));
            }

            int status = conn.getResponseCode();
            return status >= 200 && status < 300;
        } catch (Exception ignored) {
            return false;
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    private void showStatus(Context context, int notificationId, String title, String body) {
        Intent openIntent = new Intent(context, MainActivity.class);
        openIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            context,
            notificationId + 99,
            openIntent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, MainActivity.CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent);

        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.notify(notificationId == 0 ? (int) System.currentTimeMillis() : notificationId, builder.build());
        }
    }
}
