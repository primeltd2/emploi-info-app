# Deploiement backend

Le backend est pret pour Render ou Railway, mais le compte doit etre cree au nom du proprietaire du projet.

## Service Render actuel

- Service ID : `srv-d81t3cf7f7vs73eflp6g`
- URL : `https://emploi-info-api.onrender.com`
- Health check : `https://emploi-info-api.onrender.com/api/v1/health`

## Variables d'environnement

```text
NODE_ENV=production
PORT=10000
APP_ORIGIN=https://emploi-info.page.gd,https://app.local,null
DATA_DIR=..
API_KEY=
```

## Render

1. Creer un compte Render au nom du proprietaire.
2. Connecter le depot Git du projet.
3. Utiliser `backend-api/render.yaml` ou creer un Web Service :
   - Root directory : `backend-api`
   - Build command : `npm install`
   - Start command : `npm start`
4. Configurer les variables d'environnement.
5. Tester `https://.../api/v1/health`.

## Application Android autonome

- L'APK charge les pages embarquees avec l'origine interne `https://app.local`.
- Render doit garder `https://app.local` dans `APP_ORIGIN`.
- Les versions d'application sont lues via `/api/v1/app-version?kind=public` et `/api/v1/app-version?kind=admin`.
- Les tokens Firebase Android sont envoyes vers `/api/v1/android/tokens`.
- Les APK de mise a jour doivent etre publies dans GitHub Releases avec les noms `emploi-info-autonome.apk` et `admin-emploi-info-autonome.apk`.

## Railway

1. Creer un compte Railway au nom du proprietaire.
2. Creer un service depuis le depot Git.
3. Root directory : `backend-api`.
4. Railway peut utiliser `backend-api/railway.json`.
5. Tester `/api/v1/health`, `/api/v1/offers`, `/api/v1/catalog`.

## Important donnees

Aujourd'hui l'API lit les JSON depuis le dossier parent. Pour un deploiement cloud reel, il faudra soit :

- synchroniser/importer les JSON dans une base de donnees ;
- soit embarquer temporairement les JSON en lecture seule ;
- soit garder une etape intermediaire avec un stockage persistant.

La solution recommandee pour la suite est Firestore ou MongoDB Atlas afin d'eviter les pertes et les conflits d'ecriture.
