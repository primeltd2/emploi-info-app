# EMPLOI INFO Android

Application Android hybride pour EMPLOI INFO :

- ouvre les pages embarquees dans l'APK via `https://app.local/...` ;
- fonctionne sans acces au site public pour les pages, images et fichiers JSON inclus ;
- synchronise les annonces avec `https://emploi-info-api.onrender.com/api/v1` quand l'API est disponible ;
- retombe sur les fichiers embarques quand l'API est indisponible ;
- remplace les scripts serveur absents par une reponse locale hors ligne ;
- garde le code Firebase disponible, mais l'enregistrement du token et la verification de mise a jour distante sont desactives par `ENABLE_REMOTE_SITE_SERVICES=false` ;
- recoit les notifications natives Firebase Cloud Messaging si les services distants sont reactives ;
- utilise le canal Android `emploi_info_alerts` avec le son `res/raw/emploi_info_notification.mp3`.

## Configuration obligatoire

1. Creer un projet Firebase Android avec le package :
   `com.emploiinfo.app`

2. Telecharger `google-services.json` depuis Firebase et le placer ici :
   `android-app/app/google-services.json`

3. Ajouter le son de notification :
   `android-app/app/src/main/res/raw/emploi_info_notification.mp3`

4. Cote serveur, placer la cle de compte de service Firebase ici :
   `C:/htdocs/firebase_service_account.json`

   Le fichier doit venir de Firebase Console > Parametres du projet > Comptes de service.

5. Compiler dans Android Studio ou avec Gradle :
   `./gradlew assemblePublicAppRelease`

## Notes importantes

- Le site web existant n'est pas necessaire pour lire le contenu embarque dans l'APK.
- Les publications d'annonces depuis l'application admin sont envoyees a l'API et deviennent visibles dans l'application publique.
- Les actions qui dependent d'autres anciens scripts PHP repondent encore en mode hors ligne tant que leurs endpoints API equivalents ne sont pas ajoutes.
- Les notifications natives sont envoyees en plus des notifications web existantes si les services distants sont reactives.
- Si Firebase n'est pas configure, le contenu embarque continue de s'ouvrir sans erreur.
- Sur Android 8+, le son du canal est fixe a l'installation. Si le son change, il faut
  desinstaller puis reinstaller l'application, ou creer un nouveau canal.
