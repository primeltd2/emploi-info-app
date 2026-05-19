# EMPLOI INFO - Avancement migration

Date : 2026-05-19

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
- PostgreSQL cloud via `DATABASE_URL` pour les annonces, catalogues, tokens Android et notifications.
- JSON conserves seulement comme source d'import/mode local de secours.
- Routes admin verrouillees par `API_KEY`.

Routes disponibles :

- `GET /api/v1/health`
- `GET /api/v1/offers`
- `GET /api/v1/offers/stream`
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

Service Render cree :

- Service ID : `srv-d81t3cf7f7vs73eflp6g`
- URL : `https://emploi-info-api.onrender.com`
- Endpoint attendu : `https://emploi-info-api.onrender.com/api/v1/health`

### Etape 4 - Migration donnees PostgreSQL

Schema SQL ajoute dans `db/schema.sql`.
Scripts ajoutes :

- `npm run db:migrate`
- `npm run db:import-json`

En production, Render doit definir `DATABASE_URL`; les lectures/ecritures admin utilisent alors PostgreSQL au lieu des fichiers statiques.

### Etape 5 - Base application mobile

Android garde l'ecran actuel pour ne rien casser, mais il ne sert plus les annonces depuis les JSON embarques :

- `BuildConfig.API_BASE_URL`
- `ApiClient.java`
- interception `data.json` vers `/api/v1/offers`
- refresh automatique sans cache
- flux `/api/v1/offers/stream` pour apparition immediate des nouvelles annonces

Le prochain ecran a migrer pour une autonomie native totale est la liste UI Android sans WebView.

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
