# EMPLOI INFO Android

Application Android hybride pour EMPLOI INFO :

- ouvre `https://emploi-info.page.gd/index.html` dans une WebView ;
- enregistre le token Firebase du téléphone sur `register_android_token.php` ;
- reçoit les notifications natives Firebase Cloud Messaging ;
- utilise le canal Android `emploi_info_alerts` avec le son `res/raw/emploi_info_notification.mp3`.

## Configuration obligatoire

1. Créer un projet Firebase Android avec le package :
   `com.emploiinfo.app`

2. Télécharger `google-services.json` depuis Firebase et le placer ici :
   `android-app/app/google-services.json`

3. Ajouter le son de notification :
   `android-app/app/src/main/res/raw/emploi_info_notification.mp3`

4. Côté serveur, placer la clé de compte de service Firebase ici :
   `C:/htdocs/firebase_service_account.json`

   Le fichier doit venir de Firebase Console > Paramètres du projet > Comptes de service.

5. Compiler dans Android Studio ou avec Gradle :
   `./gradlew assembleRelease`

## Notes importantes

- Le site web existant n'est pas remplacé.
- Les notifications natives sont envoyées en plus des notifications web existantes.
- Si Firebase n'est pas configuré, le site continue de publier les offres sans erreur.
- Sur Android 8+, le son du canal est fixé à l'installation. Si le son change, il faut
  désinstaller puis réinstaller l'application, ou créer un nouveau canal.
