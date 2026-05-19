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
DATABASE_URL=postgres://...
DATABASE_SSL=true
DATABASE_POOL_MAX=20
API_KEY=
```

`API_KEY` doit etre une valeur longue et privee, configuree directement dans Render.
Ne pas la commiter dans Git. L'application admin Android doit etre compilee avec la meme valeur via
`EMPLOI_INFO_ADMIN_API_KEY`.

## Render

1. Creer un compte Render au nom du proprietaire.
2. Connecter le depot Git du projet.
3. Utiliser `backend-api/render.yaml` ou creer un Web Service :
   - Root directory : `backend-api`
   - Build command : `npm install`
   - Start command : `npm start`
4. Configurer les variables d'environnement.
5. Ajouter une base PostgreSQL Render et connecter `DATABASE_URL`.
6. Lancer une fois `npm run db:import-json` depuis un shell Render ou local avec `DATABASE_URL` pour importer les anciennes annonces.
7. Tester `https://.../api/v1/health` puis `https://.../api/v1/offers`.

Pour connecter l'admin a l'application publique :

1. Dans Render, definir `API_KEY` avec une cle secrete.
2. Compiler l'APK admin avec la meme cle :

```powershell
$env:EMPLOI_INFO_ADMIN_API_KEY="votre-cle-secrete"
.\gradlew.bat assembleAdminRelease
```

3. Compiler l'APK public normalement. Il lit les annonces publiees via `/api/v1/offers`.
4. Quand l'admin publie une annonce, elle est envoyee vers `/api/v1/admin/offers`.
5. L'app publique recharge `data.json` depuis l'API et voit les annonces publiees sans dependre du site web.

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

Le stockage de production est PostgreSQL via `DATABASE_URL`.
Les JSON historiques servent uniquement de source d'import avec `npm run db:import-json`.
Les annonces publiees sont exposees sans cache HTTP sur `/api/v1/offers` et le flux temps reel est disponible sur `/api/v1/offers/stream`.
