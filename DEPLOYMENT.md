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
BREVO_API_KEY=
MAILER_DSN=
MAIL_FROM_EMAIL=
MAIL_FROM_NAME=
CONTACT_NOTIFY_EMAIL=
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

`BREVO_API_KEY` est la clé API Brevo (`xkeysib-…`), la même variable que dans le projet catalogue. Ce n’est pas le login SMTP ni la clé SMTP.

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

Transport : API HTTP Brevo, comme le projet catalogue. Le mailer Symfony rend les templates, puis le worker appelle `https://api.brevo.com/v3/smtp/email` avec l’en-tête `api-key`. Il n’y a pas de username SMTP.

```text
BREVO_API_KEY=
MAILER_DSN=brevo+api://default
MAIL_FROM_EMAIL=no-reply@domaine
MAIL_FROM_NAME="Diaré Groupe Industrie"
CONTACT_NOTIFY_EMAIL=
```

Le formulaire de contact :

- enregistre le message en base (`contact_request`) avant tout envoi
- expéditeur : `MAIL_FROM_NAME <MAIL_FROM_EMAIL>`
- alerte administrateur, dans cet ordre, sans adresse inventée :
  1. `CONTACT_NOTIFY_EMAIL`, s’il est renseigné et valide ;
  2. email public du site (`SiteSettings.email`)
- `ADMIN_EMAIL` n’est pas un destinataire de cette alerte
- `Reply-To` de l’alerte : l’adresse saisie par le visiteur
- accusé de réception au visiteur, même si aucun destinataire administrateur n’est configuré
- `Reply-To` de l’accusé : l’email public du site, seulement s’il est valide ; sinon aucun `Reply-To`
- sans destinataire administrateur : le message reste en base, l’accusé visiteur part si le transport fonctionne, et un avertissement est journalisé sans le contenu du message
- l’échec d’un des deux envois n’empêche pas la tentative de l’autre et ne supprime pas le message
- un second envoi identique (même email, objet et message) dans la minute ne crée pas un second message

`CONTACT_NOTIFY_EMAIL` vide est accepté : la réception retombe alors sur l’email du back-office. Le tableau de bord signale l’absence de destinataire lorsque les deux sources sont vides. Ne pas y placer une adresse fictive.

## 11. DNS Brevo

À faire dans le compte Brevo réel. Ne pas inventer les enregistrements : les valeurs SPF, DKIM et DMARC affichées par Brevo sont les seules à copier.

1. Ajouter le domaine d’expédition.
2. Authentifier le domaine.
3. Publier le SPF indiqué par Brevo.
4. Publier le ou les DKIM indiqués par Brevo.
5. Publier le DMARC indiqué par Brevo.
6. Créer et valider l’expéditeur `no-reply@domaine`.

Brevo n’héberge pas les boîtes. `contact@`, `direction@` et `commercial@` restent des boîtes professionnelles séparées. Le site envoie depuis `no-reply@` vers `contact@` sans supposer que les deux sont chez Brevo.

Les messages transactionnels partagent `backend/templates/email/layout.html.twig` (tableaux HTML, styles inline). Le logo n’est inséré que si `DEFAULT_URI` est une origine `https` publique : logo des réglages du site, sinon `/brand/logo.png`. Une URL `localhost`, privée ou non HTTPS laisse le nom de l’entreprise en texte. Aucun CV n’est joint.

SPF, DKIM et DMARC ne sont pas contrôlés par l’application. Les valeurs à publier sont celles affichées par Brevo pour le domaine de `MAIL_FROM_EMAIL`. Ne pas les inventer, et ne pas considérer l’envoi comme authentifié tant que le compte Brevo n’affiche pas le domaine comme authentifié.

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

Les médias publics sont dans `backend/public/uploads/media` (VichUploader, préfixe public `/uploads/media`).

Les CV de candidature sont privés, dans `backend/var/private/job-applications`. Ce dossier est hors de `public/`, donc Apache ne peut pas le servir par URL. Le déploiement est un `git pull` dans le même dossier : `var/` n’est pas dans Git et n’est pas vidé par `deploy.sh`. En Docker local, `./backend` est monté dans le conteneur, le dossier privé reste donc sur l’hôte.

`deploy.sh` crée le dossier en `2770` (`www-data`), puis resserre les fichiers en `640` après le `chmod` général de `var/`. Aucun volume Docker supplémentaire n’est nécessaire tant que le code reste monté ou déployé sur place.

PHP accepte déjà 12 Mo (`docker/php/php.ini`, `upload_max_filesize` et `post_max_size`). Le formulaire refuse un CV au-delà de 5 Mo (5 242 880 octets).

Notification RH, dans cet ordre, sans adresse inventée :

1. email de candidature de l’offre, s’il est valide ;
2. `JOB_APPLICATION_NOTIFY_EMAIL`, s’il est renseigné dans l’environnement ;
3. email public du site (`SiteSettings`).

S’il n’y a aucune adresse, le dossier est quand même enregistré et l’accusé candidat part. L’échec d’envoi ne supprime pas la candidature. Les CV ne sont pas joints aux e-mails.

Anti-spam du formulaire de candidature : CSRF, champ caché, 8 envois / 30 minutes / IP (limiteur `job_application`, distinct du contact), reCAPTCHA v3 avec l’action `apply`. Une même adresse peut postuler à plusieurs offres. Un second envoi identique dans la minute ne crée pas un second dossier.

## 17 bis. Conservation des candidatures

Aucune durée légale n’est codée en dur. Le cadre applicable à Diaré Groupe Industrie (Guinée, et le droit qui régit réellement l’entreprise) doit être confirmé avec un conseil. La case du formulaire informe le candidat ; elle ne constitue pas, à elle seule, une mise en conformité.

La politique de confidentialité du site est encore un texte à renseigner depuis le back-office. Elle devrait préciser, au minimum : responsable du traitement, finalité du recrutement, destinataires (équipe RH), durée, droits d’accès, de rectification et de suppression, et le fait que le CV n’est pas public.

`JOB_APPLICATION_RETENTION_MONTHS` vide : aucune date de fin n’est enregistrée.

Quand une durée est décidée, renseigner le nombre de mois. Les nouvelles candidatures reçoivent alors `retention_until`. Les anciennes ne sont pas modifiées.

La purge n’est pas planifiée :

```bash
cd /var/www/diare-groupe-industrie/backend
sudo -u www-data php bin/console app:job-applications:purge --env=prod --no-debug
sudo -u www-data php bin/console app:job-applications:purge --execute --env=prod --no-debug
```

Sans `--execute`, la commande ne supprime rien. Avec `--execute`, elle retire le dossier et le PDF seulement si la date de fin est dépassée. Une demande d’effacement se traite dans l’administration : ouvrir la candidature, puis la supprimer. Le PDF est supprimé avec le dossier. Il n’y a pas d’espace candidat.

Sauvegarde des CV : `bin/backup.sh` produit aussi `daily/cv-AAAAMMJJ-HHMMSS.tar.gz`. Restauration :

```bash
sudo tar -C /var/www/diare-groupe-industrie/backend/var/private -xzf cv-CHOISI.tar.gz
sudo chown -R www-data:www-data /var/www/diare-groupe-industrie/backend/var/private
sudo find /var/www/diare-groupe-industrie/backend/var/private -type d -exec chmod 2770 {} +
sudo find /var/www/diare-groupe-industrie/backend/var/private -type f -exec chmod 640 {} +
```

Migration à appliquer avec le déploiement habituel, après sauvegarde :

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug
```

Elle crée `job_application` et ne modifie pas les offres existantes. La suppression d’une offre laisse les candidatures, avec l’intitulé enregistré au moment du dépôt.

Le déploiement est un `git pull` dans le même dossier. `var/private` est ignoré par Git. `git pull` ne le supprime pas. `deploy.sh` ne fait aucun `rm` dessus : il crée le dossier s’il manque, puis pose le propriétaire `www-data` et les droits `2770` pour les dossiers et `640` pour les fichiers.

Il n’y a pas de schéma `releases/current` : le VPS de référence n’en utilise pas.

## 18. Sauvegardes

```bash
sudo ./bin/backup.sh
```

Destination par défaut : `/var/backups/diare-groupe-industrie`.

- `daily/db-AAAAMMJJ-HHMMSS.sql.gz` — `diare_groupe_industrie` seulement
- `daily/media-AAAAMMJJ-HHMMSS.tar.gz` — `public/uploads/media`
- `daily/cv-AAAAMMJJ-HHMMSS.tar.gz` — `var/private/job-applications`, si le dossier existe
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

Aucun cron métier. Les commandes `app:*` sont manuelles : `app:create-admin`, `app:generate-media-thumbnails`, `app:seed-editorial-demo`, `app:job-applications:purge`.

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
