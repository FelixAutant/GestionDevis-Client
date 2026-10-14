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

## Démo publique

Pour exposer une démo depuis un portfolio : utiliser une base **séparée**, y charger les fixtures
(`php bin/console doctrine:fixtures:load`) et ne publier que le compte de démonstration. Les fixtures
purgent la base : ne jamais les lancer sur une base contenant de vraies données.
