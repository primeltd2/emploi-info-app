# EMPLOI INFO API

Backend Express parallele pour migrer l'application sans casser le PHP existant.

## Demarrage local

```bash
npm install
npm run dev
```

URL locale par defaut :

```text
http://localhost:4000/api/v1
```

## Routes lecture seule disponibles

- `GET /api/v1/health`
- `GET /api/v1/offers?limit=30&category=...&city=...`
- `GET /api/v1/offers/:id`
- `GET /api/v1/catalog`
- `GET /api/v1/ads`
- `GET /api/v1/app-version?kind=public`
- `GET /api/v1/app-version?kind=admin`
- `POST /api/v1/android/tokens`

Exemple token Android :

```json
{
  "token": "token-firebase",
  "platform": "android",
  "app": "EMPLOI INFO"
}
```

## Routes admin preparees

Ces routes sont protegees par l'en-tete `x-api-key`.
Elles restent inactives tant que `API_KEY` n'est pas configure.

- `GET /api/v1/admin/offers`
- `POST /api/v1/admin/offers`
- `PATCH /api/v1/admin/offers/:id`
- `PATCH /api/v1/admin/offers/:id/publish`
- `DELETE /api/v1/admin/offers/:id`

Exemple creation :

```json
{
  "title": "Titre annonce",
  "description": "Description complete",
  "notice": "Resume court",
  "category": "Emploi",
  "city": "Cotonou",
  "published": false
}
```

## Strategie de migration

Le backend lit actuellement les fichiers JSON existants dans `C:\htdocs`.
Les anciennes pages PHP continuent donc de fonctionner.

Prochaine phase :

1. ajouter les routes d'ecriture protegees ;
2. migrer les JSON vers Firestore, MongoDB ou MySQL ;
3. connecter progressivement l'app Android aux routes `/api/v1`.
