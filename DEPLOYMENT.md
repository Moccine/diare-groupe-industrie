# Déploiement — Diaré Groupe Industrie

Déploiement natif sur le VPS déjà utilisé par les autres sites. Diaré a son propre dossier, son VirtualHost, ses logs, son worker, sa base et ses sauvegardes. Rien dans cette procédure ne modifie un autre site.

Docker reste l’environnement de développement local. Il n’est pas le modèle de production.

## 1. Prérequis

Sur le VPS, sans changer la version de PHP des autres applications :

- PHP 8.2.7 ou supérieur, avec `ctype`, `iconv`, `pdo_mysql`, `mbstring`, `xml`, `intl`, `gd` (WebP), `zip`, `curl`, `fileinfo`, `tokenizer`
- Composer 2
- Node.js et npm
- Apache 2 avec `rewrite`
- MariaDB, base `diare_groupe_industrie`, utilisateur `diare_prod`
- Certbot, au moment du HTTPS seulement
- `mariadb-dump` ou `mysqldump` pour les sauvegardes

`bin/install.sh` vérifie ces éléments. Il n’installe rien et ne lance pas `apt`.

## 2. Architecture

```text
/var/www/diare-groupe-industrie
├── backend/          Symfony 7.4, DocumentRoot = backend/public
├── frontend/         sources SCSS et JavaScript
├── bin/              install, deploy, vhost, validate, backup
└── docker/           développement local uniquement
```

Le build Webpack Encore se lance dans `backend/` (`package.json` et `package-lock.json` y sont).

## 3. Chemin serveur

```text
/var/www/diare-groupe-industrie
```

DocumentRoot :

```text
/var/www/diare-groupe-industrie/backend/public
```

Propriétaire applicatif : `www-data:www-data`.

## 4. Base de données

| | Production | Docker local |
|---|---|---|
| Base | `diare_groupe_industrie` | `diare_groupe` |
| Utilisateur | `diare_prod` | `symfony` |

`diare_groupe` n’est pas un reste à corriger en production : c’est uniquement la base du `docker-compose.yaml` de développement. Les scripts de production refusent toute autre base, et refusent l’utilisateur `root`.

Le mot de passe reste dans `backend/.env.local` sur le serveur, jamais dans Git.

Doctrine utilise `serverVersion=mariadb-11.4.0`.

Migrations à appliquer, dans l’ordre déjà versionné :

- `Version20261004140754`
- `Version20261004173243`
- `Version20261004180000`
- `Version20261004193000`
- `Version20261005115356`
- `Version20261008120000` — table `messenger_messages`

Commande :

```bash
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
```

Ne jamais exécuter `doctrine:schema:drop`, `doctrine:database:drop`, `doctrine:schema:create` ou `doctrine:schema:update` en production.

Ne jamais lancer `doctrine:fixtures:load` ni `app:seed-editorial-demo` en production.

## 5. Variables d’environnement

Modèle : `backend/.env.prod.dist`.

Sur le serveur uniquement :

```bash
cp backend/.env.prod.dist backend/.env.local
chmod 640 backend/.env.local
chown www-data:www-data backend/.env.local
```

Variables à remplir :

```text
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=
APP_TIMEZONE=Europe/Paris
DEFAULT_URI=
DATABASE_URL=
BREVO_SMTP_LOGIN=
BREVO_SMTP_KEY=
MAILER_DSN=
MAIL_FROM_EMAIL=
MAIL_FROM_NAME=
RECAPTCHA_ENABLED=
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
RECAPTCHA_MIN_SCORE=0.5
GOOGLE_ANALYTICS_MEASUREMENT_ID=
APP_INDEXABLE=1
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

`DEFAULT_URI` est l’URL canonique (`https://domaine` ou `https://www.domaine`). Elle sert aux URL absolues hors requête HTTP : e-mails, sitemap en CLI, Messenger. En HTTP, le host de la requête est utilisé, et la variante www / apex est redirigée vers ce host canonique.

Préproduction : un autre dossier ou le même dossier avec un autre `DEFAULT_URI`, et `APP_INDEXABLE=0`. Le domaine n’est pas codé en dur.

`APP_SECRET` : au moins 32 caractères, différent du placeholder.

Clé SMTP Brevo : si elle contient `@`, `:`, `/`, `?`, `#`, `%` ou un espace, coller la valeur encodée une seule fois :

```bash
php -r 'echo rawurlencode($argv[1]), PHP_EOL;' 'la-cle-smtp'
```

## 6. Premier déploiement

```bash
sudo mkdir -p /var/www/diare-groupe-industrie
sudo git clone <dépôt> /var/www/diare-groupe-industrie
cd /var/www/diare-groupe-industrie
sudo ./bin/install.sh
# remplir backend/.env.local
sudo ./bin/create-apache-vhost.sh \
  --domain DOMAINE \
  --server-admin EMAIL \
  --www
sudo DEPLOY_SKIP_GIT=1 SKIP_HTTP_CHECK=1 ./bin/deploy.sh
sudo ./bin/validate.sh
```

`DEPLOY_SKIP_GIT=1` sert si le clone vient d’être fait et qu’un second pull est inutile. `SKIP_HTTP_CHECK=1` sert tant que le DNS ne pointe pas encore vers le VPS.

Créer l’administrateur à la main, une fois la base migrée :

```bash
cd /var/www/diare-groupe-industrie/backend
sudo -u www-data php bin/console app:create-admin --env=prod
```

Renseigner `ADMIN_EMAIL` et `ADMIN_PASSWORD` dans `.env.local` avant cette commande. Le déploiement ne crée jamais de compte.

Les contenus initiaux (pages, réglages, catégories) ne sont pas injectés automatiquement. Les saisir dans `/administration`, ou les importer par une opération décidée explicitement. `SiteSettings` absent n’empêche pas le site de répondre : un socle de secours s’affiche, avec l’e-mail marqué « à renseigner ».

## 7. Déploiements suivants

```bash
cd /var/www/diare-groupe-industrie
sudo BACKUP_BEFORE_MIGRATE=1 ./bin/deploy.sh
sudo ./bin/validate.sh
```

`bin/deploy.sh` enchaîne : arrêt du worker, `git pull --ff-only`, permissions, `composer install --no-dev --optimize-autoloader --classmap-authoritative`, contrôle d’environnement, migrations, `npm ci`, `npm run build`, cache, relance du worker, `apache2ctl configtest`, contrôle HTTP.

Sans `BACKUP_BEFORE_MIGRATE=1`, le script prévient et continue. Il ne lance pas un dump tout seul.

Les fixtures ne font pas partie du déploiement.

## 8. Apache

Fichier généré : `/etc/apache2/sites-available/DOMAINE.conf`.

- `ServerName` = la valeur de `--domain` (URL canonique)
- `ServerAlias` = l’autre variante si `--www` (ou `--alias`)
- redirection 301 de l’alias vers le domaine canonique
- `DocumentRoot` = `.../backend/public`
- `AllowOverride None`, rewrite vers `index.php`
- logs : `/var/log/apache2/diare-groupe-industrie-DOMAINE-error.log` et `-access.log`

Le script refuse d’écraser un VirtualHost dont le `DocumentRoot` n’est pas celui de Diaré. Il ne désactive aucun autre site.

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

`create-apache-vhost.sh` ne recharge Apache que si `configtest` affiche une syntaxe valide.

## 9. SSL

À lancer seulement quand le domaine existe, le DNS pointe vers le VPS, et Apache répond en HTTP.

```bash
sudo ./bin/create-apache-vhost.sh \
  --domain DOMAINE \
  --server-admin EMAIL \
  --www \
  --ssl
```

Équivalent direct :

```bash
sudo certbot --apache --non-interactive --agree-tos --redirect \
  --email EMAIL \
  -d DOMAINE -d www.DOMAINE
```

Le renouvellement est le timer Certbot du système (`systemctl status certbot.timer`). Ne pas ajouter un second cron de renouvellement.

HSTS, seulement après plusieurs jours de HTTPS stable, dans le VirtualHost SSL :

```apache
Header always set Strict-Transport-Security "max-age=31536000"
```

Si un certificat existe déjà, le script s’arrête. `--force` réécrit le vhost HTTP : ne l’utiliser qu’en sachant que Certbot devra être rejoué.

## 10. Brevo

Transport : SMTP Symfony Mailer, pas l’API HTTP Brevo, pas Mailjet.

```text
BREVO_SMTP_LOGIN=
BREVO_SMTP_KEY=
MAILER_DSN="smtp://${BREVO_SMTP_LOGIN}:${BREVO_SMTP_KEY}@smtp-relay.brevo.com:587?require_tls=true"
MAIL_FROM_EMAIL=no-reply@domaine
MAIL_FROM_NAME="Diaré Groupe Industrie"
```

Le formulaire de contact :

- enregistre le message en base (`contact_request`)
- expéditeur : `MAIL_FROM_NAME <MAIL_FROM_EMAIL>`
- notification interne vers `SiteSettings.email` (back-office « Coordonnées et réglages »)
- `Reply-To` de cette notification : l’adresse saisie par le visiteur
- accusé de réception au visiteur, dont le `Reply-To` est `SiteSettings.email`
- pas d’envoi si `SiteSettings.email` est vide

Il n’y a pas de variable `CONTACT_EMAIL` : l’adresse de réception a une seule source, le back-office.

## 11. DNS Brevo

À faire dans le compte Brevo réel. Ne pas inventer les enregistrements : les valeurs SPF, DKIM et DMARC affichées par Brevo sont les seules à copier.

1. Ajouter le domaine d’expédition.
2. Authentifier le domaine.
3. Publier le SPF indiqué par Brevo.
4. Publier le ou les DKIM indiqués par Brevo.
5. Publier le DMARC indiqué par Brevo.
6. Créer et valider l’expéditeur `no-reply@domaine`.

Brevo SMTP n’héberge pas les boîtes. `contact@`, `direction@` et `commercial@` restent des boîtes professionnelles séparées. Le site envoie depuis `no-reply@` vers `contact@` sans supposer que les deux sont chez Brevo.

## 12. reCAPTCHA

Déjà en place. Ne pas le réécrire.

- Front : `frontend/assets/js/modules/recaptcha-form.js`, action `contact`
- Back : `App\Service\RecaptchaVerifier` appelle `siteverify`, refuse un jeton vide, une action autre que `contact`, un score sous `RECAPTCHA_MIN_SCORE`, un timeout ou une erreur Google
- Secret uniquement côté serveur
- `RECAPTCHA_ENABLED=1` sans clés : le formulaire public est refusé

Clés : console Google reCAPTCHA v3, domaines de production et de préproduction.

## 13. Google Analytics 4

`GOOGLE_ANALYTICS_MEASUREMENT_ID` vide : aucun script, aucune bannière.

Renseigné (`G-…`) : une bannière propose Accepter ou Refuser. Le script `gtag` n’est écrit qu’après `analytics_consent=accepted` (cookie, 180 jours, `SameSite=Lax`, `Secure` en HTTPS). Le lien « Mesure d’audience » du pied de page efface ce choix.

Le texte de la page « Politique de confidentialité » est éditorial. Quand Analytics est activé, y indiquer depuis le back-office le cookie `analytics_consent` et l’outil Google Analytics 4.

## 14. Search Console

Aucun SDK dans le projet. Après la mise en ligne HTTPS :

1. Créer la propriété du domaine.
2. Valider le domaine.
3. Déclarer `https://DOMAINE/sitemap.xml`.

Le site expose `/robots.txt`, `/sitemap.xml`, une URL canonique et la redirection www / apex.

## 15. Messenger

`config/packages/messenger.yaml` :

- transport `async` : `MESSENGER_TRANSPORT_DSN`
- production : `doctrine://default?auto_setup=0`
- développement : `sync://` (Mailpit immédiat, sans worker)
- routage : `SendEmailMessage` vers `async`
- échecs : `doctrine://default?queue_name=failed&auto_setup=0`

Les deux files utilisent la table `messenger_messages`. Elle est créée par la migration `Version20261008120000`, pas par `auto_setup`.

Inspection :

```bash
php bin/console messenger:failed:show --env=prod
php bin/console messenger:failed:retry --env=prod
php bin/console messenger:failed:remove ID --env=prod
```

## 16. Worker systemd

`bin/deploy.sh` écrit un service indépendant :

```text
diare-groupe-industrie-messenger.service
```

Si le dossier n’est pas `/var/www/diare-groupe-industrie`, le nom reçoit le nom du dossier, pour ne pas remplacer le worker de production lors d’une préproduction séparée.

```ini
User=www-data
Group=www-data
WorkingDirectory=/var/www/diare-groupe-industrie/backend
Environment=APP_ENV=prod
Environment=APP_DEBUG=0
ExecStart=/usr/bin/php8.2 bin/console messenger:consume async --time-limit=3600 --memory-limit=256M --sleep=1 --env=prod --no-debug -vv
Restart=always
RestartSec=5
```

Les secrets restent dans `.env.local`, lus par Symfony. Ils ne sont pas dans l’unité systemd.

```bash
systemctl status diare-groupe-industrie-messenger
journalctl -u diare-groupe-industrie-messenger -n 100 --no-pager
```

## 17. Uploads

Les médias sont dans `backend/public/uploads/media` (VichUploader, préfixe public `/uploads/media`).

Le déploiement est un `git pull` dans le même dossier, comme BienChezVousOise. Ce répertoire est ignoré par Git, hors le fichier `.gitkeep`. `git pull` ne le supprime pas. `deploy.sh` ne fait aucun `rm` dessus : il crée le dossier s’il manque, puis pose le propriétaire `www-data` et les droits `2775` / `664`.

Il n’y a pas de schéma `releases/current` : le VPS de référence n’en utilise pas.

## 18. Sauvegardes

```bash
sudo ./bin/backup.sh
```

Destination par défaut : `/var/backups/diare-groupe-industrie`.

- `daily/db-AAAAMMJJ-HHMMSS.sql.gz` — `diare_groupe_industrie` seulement
- `daily/media-AAAAMMJJ-HHMMSS.tar.gz` — `public/uploads/media`
- le dimanche, copie dans `weekly/`
- conservation : 7 quotidiennes, 4 hebdomadaires

Le mot de passe est lu depuis `.env.local` vers un fichier temporaire `0600`, jamais écrit dans le script ni affiché. Le script s’arrête si la base n’est pas `diare_groupe_industrie` ou si l’utilisateur est `root`.

## 19. Restauration

```bash
sudo systemctl stop diare-groupe-industrie-messenger
gunzip -c /var/backups/diare-groupe-industrie/daily/db-CHOISI.sql.gz \
  | mariadb --defaults-extra-file=/chemin/temporaire.cnf diare_groupe_industrie
sudo tar -C /var/www/diare-groupe-industrie/backend/public/uploads -xzf media-CHOISI.tar.gz
cd /var/www/diare-groupe-industrie/backend
sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo chown -R www-data:www-data var public/uploads
sudo find var public/uploads -type d -exec chmod 2775 {} +
sudo find var public/uploads -type f -exec chmod 664 {} +
sudo systemctl start diare-groupe-industrie-messenger
```

Le fichier d’identifiants temporaire se construit comme dans `bin/backup.sh`, puis se supprime.

## 20. Administration

URL : `/administration`, connexion `/administration/connexion`.

Accès : `ROLE_ADMIN`. Le formulaire de connexion a un jeton CSRF. Cinq tentatives par 15 minutes.

Commande du premier compte : `app:create-admin`. Elle met à jour le compte dont l’e-mail est `ADMIN_EMAIL`. Elle n’est pas appelée par `deploy.sh`.

## 21. Logs

- Apache : `/var/log/apache2/diare-groupe-industrie-DOMAINE-error.log` et `-access.log`
- Symfony production : JSON sur stderr, donc le journal Apache / PHP, niveau erreur (les 404 ne déclenchent pas le tampon)
- worker : `journalctl -u diare-groupe-industrie-messenger`
- e-mails, reCAPTCHA et Messenger : canaux Monolog, sans mot de passe, clé Brevo ni secret reCAPTCHA

## 22. Dépannage

| Symptôme | Piste |
|---|---|
| Page blanche après deploy | `var/log`, log Apache, `php bin/console cache:clear --env=prod` |
| CSS/JS absents | `backend/public/build/entrypoints.json`, relancer `npm ci && npm run build` dans `backend/` |
| 500 base de données | `DATABASE_URL`, utilisateur `diare_prod`, `doctrine:migrations:status --env=prod` |
| E-mails immobiles | worker actif, table `messenger_messages`, `messenger:failed:show` |
| Formulaire refusé | clés reCAPTCHA, score, rate limit 5 / 15 min / IP |
| www et apex tous les deux ouverts | `DEFAULT_URI` doit être l’URL canonique exacte |
| Préproduction indexée | `APP_INDEXABLE=0` |

Contrôle :

```bash
sudo ./bin/validate.sh
```

## Permissions

```bash
sudo chown -R www-data:www-data /var/www/diare-groupe-industrie
sudo find /var/www/diare-groupe-industrie/backend/var \
  /var/www/diare-groupe-industrie/backend/public/uploads \
  -type d -exec chmod 2775 {} +
sudo find /var/www/diare-groupe-industrie/backend/var \
  /var/www/diare-groupe-industrie/backend/public/uploads \
  -type f -exec chmod 664 {} +
sudo chmod 640 /var/www/diare-groupe-industrie/backend/.env.local
```

Pas de `chmod -R 777`. Le bit setgid (`2775`) fait hériter le groupe `www-data`.

## Cron

Aucun cron métier. Les commandes `app:*` sont manuelles : `app:create-admin`, `app:generate-media-thumbnails`, `app:seed-editorial-demo`.

La dernière ne doit pas être planifiée : elle ajoute des contenus de démonstration.

## Rollback

Le rollback de code :

```bash
cd /var/www/diare-groupe-industrie
sudo git checkout <commit-précédent>
sudo DEPLOY_SKIP_GIT=1 ./bin/deploy.sh
```

Cela réinstalle Composer, reconstruit les assets, vide le cache et relance le worker.

Une migration déjà exécutée n’est pas annulée par ce retour de code. Si elle a modifié des données, la restauration se fait depuis `bin/backup.sh`, pas par un `doctrine:migrations:migrate prev` supposé sans risque.

## Bascule du domaine

1. Installer le site sur le VPS.
2. Le tester sur un host de préproduction (`APP_INDEXABLE=0`).
3. Vérifier la base, les médias, l’admin.
4. Envoyer un message de contact et contrôler la notification, l’accusé et le `Reply-To`.
5. Vérifier Brevo (domaine authentifié).
6. Vérifier reCAPTCHA sur le domaine réel.
7. Vérifier Analytics seulement après consentement.
8. Baisser le TTL DNS si nécessaire.
9. Faire pointer le DNS vers le VPS.
10. Lancer Certbot.
11. Vérifier la redirection www / apex.
12. Créer la propriété Search Console et déclarer le sitemap.
13. Surveiller HTTP, certificat, disque, worker, logs Apache, logs Symfony, MariaDB, volume des uploads.

Aucun outil de monitoring supplémentaire n’est installé : utiliser celui qui surveille déjà le VPS.

## Headers

Posés par l’application : `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy`.

En production, `APP_DEBUG=0` désactive aussi le `X-Robots-Tag: noindex` que Symfony ajoute automatiquement lorsque le debug est actif. La préproduction s’appuie sur `APP_INDEXABLE=0`, pas sur ce comportement de debug.

## Sécurité des cookies de session

En production : `Secure=auto`, `HttpOnly`, `SameSite=Lax`. Le profiler Symfony n’est pas chargé hors développement.
