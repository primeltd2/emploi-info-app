# Conservation des fonctionnalites existantes

Objectif : migrer vite sans supprimer ce qui fonctionne deja.

## Conserve actuellement

- Site web public existant.
- Panneau admin HTML/PHP existant.
- Toutes les pages HTML actuelles.
- Tous les scripts PHP actuels.
- Donnees JSON actuelles.
- Uploads et images existants.
- Firebase Cloud Messaging Android.
- Notifications web push existantes.
- AdMob banner/interstitial/native/app-open.
- APK actuel et build Gradle.
- Connexion admin existante.
- Formulaires et liens existants via WebView.

## Ajoute sans casser

- Backend Express parallele.
- API publique lecture seule pour annonces, catalogue, pubs, version app.
- API admin protegee par `API_KEY`, inactive si la cle n'est pas configuree.
- Ecriture JSON atomique cote Node pour eviter les fichiers partiellement ecrits.
- Client Android `ApiClient.java`, non impose a l'interface actuelle.

## Regle de migration

La WebView reste le filet de securite tant que toutes les fonctions natives ne sont pas reconstruites.
On bascule une fonctionnalite vers le natif uniquement quand elle est :

1. disponible via API ;
2. testee localement ;
3. compatible avec les donnees existantes ;
4. couverte par un retour vers l'ancien comportement.

## Ordre conseille

1. Liste des annonces native.
2. Detail annonce natif.
3. Recherche et filtres natifs.
4. Publicites controlees.
5. Notifications et ouverture directe d'annonce.
6. Connexion utilisateur si elle doit etre reactivee.
7. Admin mobile ou admin web separe securise.
