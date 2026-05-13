# EMPLOI INFO - Avancement migration

Date : 2026-05-12

## Termine maintenant

### Etape 2 - Architecture backend

Backend Node.js + Express cree dans `C:\htdocs\backend-api`.

Structure :

- `src/app.js`
- `src/server.js`
- `src/routes`
- `src/controllers`
- `src/services`
- `src/repositories`
- `src/middlewares`
- `src/validators`
- `src/config`

Securite et stabilite :

- Helmet active.
- CORS controle par `APP_ORIGIN`.
- Rate limit global.
- Validation Zod.
- Gestion centralisee des erreurs.
- Cache leger des JSON par date de modification.
- Ecriture JSON atomique pour les futures actions admin.
- Routes admin verrouillees par `API_KEY`.

Routes disponibles :

- `GET /api/v1/health`
- `GET /api/v1/offers`
- `GET /api/v1/offers/:id`
- `GET /api/v1/catalog`
- `GET /api/v1/ads`
- `GET /api/v1/app-version`
- `GET /api/v1/admin/offers`
- `POST /api/v1/admin/offers`
- `PATCH /api/v1/admin/offers/:id`
- `PATCH /api/v1/admin/offers/:id/publish`
- `DELETE /api/v1/admin/offers/:id`

Les routes `/admin` restent inactives tant que `API_KEY` n'est pas configure.

### Etape 3 - Preparation hebergement

Fichiers ajoutes :

- `render.yaml`
- `railway.json`
- `docs/DEPLOYMENT.md`
- `.env.example`

Le deploiement reel necessite la creation du compte Render ou Railway au nom du proprietaire.

### Etape 4 - Preparation migration donnees

L'API lit actuellement les fichiers JSON existants sans les modifier.
Cela permet une migration progressive sans perte de donnees et sans casser PHP.

Prochaine action donnees :

1. choisir Firestore, MongoDB Atlas ou MySQL ;
2. creer scripts d'import depuis JSON ;
3. basculer d'abord les lectures API ;
4. basculer ensuite les ecritures admin.

### Etape 5 - Base application mobile

Android garde l'ecran actuel pour ne rien casser, mais la base API native est ajoutee :

- `BuildConfig.API_BASE_URL`
- `ApiClient.java`

Le prochain ecran natif a migrer en priorite est la liste des annonces.

## Verifications

- `npm test` dans `backend-api` : 3 tests OK.
- `npm test` dans `backend-api` apres routes admin : 4 tests OK.
- `.\gradlew.bat :app:assemblePublicAppDebug` : build Android OK.
- APK debug genere : `C:\htdocs\android-app\app\build\outputs\apk\publicApp\debug\app-publicApp-debug.apk`

## Prochain sprint conseille

1. Deployer `backend-api` sur Render ou Railway.
2. Remplacer `API_BASE_URL` Android par l'URL reelle.
3. Creer un ecran natif Android `OffersActivity` ou un fragment liste annonces.
4. Migrer `/offers` vers Firestore ou MongoDB.
5. Ajouter JWT/admin API pour les ecritures securisees.

## Garantie compatibilite

Le mode WebView actuel reste conserve pour ne perdre aucune fonctionnalite integree.
Les nouvelles couches API et Android sont ajoutees progressivement a cote de l'existant.
Voir `docs/FEATURE_COMPATIBILITY.md`.
