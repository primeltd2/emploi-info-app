package com.emploiinfo.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioAttributes;
import android.net.Uri;
import android.os.Build;

import androidx.core.app.NotificationCompat;
import androidx.core.app.RemoteInput;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class EmploiFirebaseMessagingService extends FirebaseMessagingService {
    public static final int SUMMARY_NOTIFICATION_ID = 1000;

    @Override
    public void onNewToken(String token) {
        registerToken(token);
    }

    @Override
    public void onMessageReceived(RemoteMessage message) {
        String title = message.getData().get("title");
        String body = message.getData().get("body");
        String url = message.getData().get("url");
        String offerId = message.getData().get("offer_id");

        if (title == null || title.trim().isEmpty()) {
            title = message.getNotification() != null && message.getNotification().getTitle() != null ?
                 message.getNotification().getTitle()
                : "EMPLOI INFO";
        }
        if (body == null || body.trim().isEmpty()) {
            body = message.getNotification() != null && message.getNotification().getBody() != null ?
                 message.getNotification().getBody()
                : "Une nouvelle offre est disponible.";
        }

        if (alreadyNotified(offerId)) return;
        int unreadCount = incrementUnreadCount();
        showNotification(title, body, url, offerId, unreadCount);
        markNotified(offerId);
    }

    private boolean alreadyNotified(String offerId) {
        if (offerId == null || offerId.trim().isEmpty()) return false;
        SharedPreferences prefs = getSharedPreferences("emploi_info_notifications", MODE_PRIVATE);
        return prefs.getBoolean("offer_" + offerId, false);
    }

    private void markNotified(String offerId) {
        if (offerId == null || offerId.trim().isEmpty()) return;
        SharedPreferences prefs = getSharedPreferences("emploi_info_notifications", MODE_PRIVATE);
        prefs.edit().putBoolean("offer_" + offerId, true).apply();
    }

    private int incrementUnreadCount() {
        SharedPreferences prefs = getSharedPreferences("emploi_info_notifications", MODE_PRIVATE);
        int count = prefs.getInt("unread_count", 0) + 1;
        prefs.edit().putInt("unread_count", count).apply();
        return count;
    }

    private void showNotification(String title, String body, String url, String offerId, int unreadCount) {
        createNotificationChannel();

        Intent intent = new Intent(this, MainActivity.class);
        intent.putExtra("url", url != null ? url : MainActivity.HOME_URL);
        intent.putExtra("offer_id", offerId);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);

        PendingIntent pendingIntent = PendingIntent.getActivity(
            this,
            url != null ? url.hashCode() : 0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        int notificationId = offerId != null && !offerId.trim().isEmpty() ?
             offerId.hashCode()
            : (url != null ? url.hashCode() : (int) System.currentTimeMillis());

        Intent commentIntent = new Intent(this, NotificationActionReceiver.class);
        commentIntent.setAction(NotificationActionReceiver.ACTION_COMMENT);
        commentIntent.putExtra("offer_id", offerId);
        commentIntent.putExtra("notification_id", notificationId);

        PendingIntent commentPendingIntent = PendingIntent.getBroadcast(
            this,
            notificationId + 31,
            commentIntent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_MUTABLE
        );

        RemoteInput remoteInput = new RemoteInput.Builder(NotificationActionReceiver.KEY_COMMENT_TEXT)
            .setLabel("Votre commentaire")
            .build();

        NotificationCompat.Action commentAction = new NotificationCompat.Action.Builder(
            android.R.drawable.ic_menu_send,
            "Commenter",
            commentPendingIntent
        ).addRemoteInput(remoteInput).setAllowGeneratedReplies(true).build();

        Uri soundUri = Uri.parse("android.resource://" + getPackageName() + "/raw/emploi_info_notification");
        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, MainActivity.CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setDefaults(NotificationCompat.DEFAULT_VIBRATE | NotificationCompat.DEFAULT_LIGHTS)
            .setFullScreenIntent(pendingIntent, true)
            .setAutoCancel(true)
            .setNumber(Math.max(1, unreadCount))
            .setSound(soundUri)
            .setContentIntent(pendingIntent)
            .addAction(android.R.drawable.ic_menu_view, "Voir", pendingIntent)
            .addAction(commentAction);

        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.notify(notificationId, builder.build());
            if (unreadCount > 1) {
                NotificationCompat.Builder summary = new NotificationCompat.Builder(this, MainActivity.CHANNEL_ID)
                    .setSmallIcon(android.R.drawable.ic_dialog_info)
                    .setContentTitle("EMPLOI INFO")
                    .setContentText(unreadCount + " nouvelles notifications")
                    .setStyle(new NotificationCompat.BigTextStyle().bigText(unreadCount + " nouvelles notifications EMPLOI INFO"))
                    .setPriority(NotificationCompat.PRIORITY_HIGH)
                    .setDefaults(NotificationCompat.DEFAULT_VIBRATE | NotificationCompat.DEFAULT_LIGHTS)
                    .setAutoCancel(true)
                    .setNumber(unreadCount)
                    .setContentIntent(pendingIntent);
                manager.notify(SUMMARY_NOTIFICATION_ID, summary.build());
            }
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
            MainActivity.CHANNEL_ID,
            "Alertes EMPLOI INFO",
            NotificationManager.IMPORTANCE_HIGH
        );
        channel.enableVibration(true);
        channel.setSound(soundUri, attrs);

        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) manager.createNotificationChannel(channel);
    }

    private void registerToken(String token) {
        new Thread(() -> {
            try {
                URL url = new URL(MainActivity.REGISTER_TOKEN_URL);
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
}
