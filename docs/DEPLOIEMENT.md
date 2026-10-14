# Déploiement

L'application est un projet Symfony classique : PHP 8.3+, PostgreSQL 16, un serveur web (nginx, Caddy…) qui pointe sur `public/`.

> Ce guide décrit la procédure standard Symfony. Les étapes sont à valider sur votre hébergement
> (elles n'ont pas été rejouées sur un serveur de production dans ce dépôt).

## Variables d'environnement (production)

| Variable | Rôle |
|---|---|
| `APP_ENV=prod` | mode production |
| `APP_SECRET` | secret aléatoire (`openssl rand -hex 32`), **jamais** versionné |
| `DATABASE_URL` | `postgresql://user:motdepasse@hote:5432/base?serverVersion=16&charset=utf8` |
| `DEFAULT_URI` | URL publique de l'application |

## Étapes

```bash
composer install --no-dev --optimize-autoloader
php bin/console asset-map:compile          # compile les assets (AssetMapper, sans Node)
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
php bin/console app:user:create vous@exemple.fr "Votre entreprise"   # premier compte
```

Extensions PHP requises : `intl`, `mbstring`, `pdo_pgsql`, `gd`, `zip`, `xml`.

## Exemple de configuration nginx

```nginx
server {
    server_name devis.exemple.fr;
    root /var/www/gestiondevis/public;

    location / { try_files $uri /index.php$is_args$args; }
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        internal;
    }
    location ~ \.php$ { return 404; }
}
```

## Démo gratuite : Render + Neon

Le dépôt contient un `Dockerfile` (PHP 8.3 + Apache) et un blueprint `render.yaml`. La base PostgreSQL est
hébergée chez [Neon](https://neon.com) (offre gratuite sans expiration), l'application chez
[Render](https://render.com) (offre gratuite).

> **Ce qui a été vérifié** : l'application en mode `prod` sans dépendances de dev (migrations, seed, connexion,
> PDF, assets compilés, redirections https derrière un proxy), la configuration Apache sur l'image officielle
> `php:8.3-apache` et la syntaxe du Dockerfile. **Ce qui ne l'a pas été** : le build complet de l'image et le
> déploiement sur Render/Neon eux-mêmes (indisponibles depuis l'environnement de développement).
> Les noms de menus des plateformes peuvent avoir changé.

### 1. Base de données (Neon)

1. Créer un projet Neon (région proche de celle de Render) et copier la chaîne de connexion.
2. L'adapter au format Doctrine en ajoutant `serverVersion` et `charset` (et en gardant `sslmode=require`) :

   ```
   postgresql://UTILISATEUR:MOTDEPASSE@ep-xxx.region.aws.neon.tech/neondb?sslmode=require&serverVersion=16&charset=utf8
   ```

### 2. Application (Render)

1. *New → Blueprint*, choisir le dépôt GitHub : Render lit `render.yaml`.
2. Renseigner `DATABASE_URL` avec la chaîne ci-dessus. `APP_SECRET` est généré automatiquement.
3. Au démarrage, le conteneur applique les migrations puis, comme `DEMO_MODE=1`, charge les données de démo
   **uniquement si la base ne contient aucun utilisateur** (`app:demo:seed`, aucune purge).
4. Compte de démo : `demo@gestiondevis.test` / `demo-password`.

### Ce qu'il faut savoir sur l'offre gratuite

- Render met l'application **en veille après ~15 min sans visite** : le premier chargement peut prendre
  30 à 60 s. Neon met aussi la base en veille (réveil ~1 s). À signaler à côté du lien dans le portfolio.
- Une démo publique est modifiable par tous ses visiteurs : ne jamais y mettre de vraies données.
  Pour repartir d'une base propre, vider les tables (ou recréer la base Neon) et redémarrer le service.
- Le disque est éphémère : les sessions sont perdues à chaque redémarrage (reconnexion), sans autre conséquence.
- `TRUSTED_PROXIES=REMOTE_ADDR` fait confiance aux en-têtes `X-Forwarded-*` de l'équipement qui précède
  l'application. C'est correct derrière le répartiteur de Render, mais ne l'activer **que** derrière un proxy.

### Tester l'image en local

```bash
docker build -t gestiondevis .
docker run --rm -p 8080:80 -e APP_SECRET=$(openssl rand -hex 32) -e DEMO_MODE=1 \
  -e DATABASE_URL='postgresql://app:!ChangeMe!@host.docker.internal:5432/app?serverVersion=16&charset=utf8' \
  gestiondevis
```

> Avec le serveur intégré `php -S`, si le `php.ini` local a `variables_order = "GPCS"` (sans `E`), les variables
> d'environnement ne sont pas vues par l'application. Lancer alors `php -d variables_order=EGPCS -S …`.
