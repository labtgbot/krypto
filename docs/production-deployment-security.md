# Production Deployment Security Checklist

This checklist covers direct web access and writable path requirements for a
production Krypto deployment. It complements the upload-specific execution
guards in [docs/upload-storage-deployment.md](upload-storage-deployment.md).

## Document Root

Use a dedicated virtual host whose document root is the Krypto application
directory that contains `index.php`, `dashboard.php`, `app/`, `assets/`,
`config/`, `install/`, `public/`, and `vendor/`. Do not point a web server at a
parent directory that also contains backups, deployment archives, database
dumps, CI logs, or other applications.

The current legacy layout serves public entry points and assets from the
application root, so a `public/`-only document root is not supported without
rewriting entry points, asset URLs, and bootstrap paths. Production deployments
must therefore combine the application-root document root with deny rules for
sensitive directories and metadata.

Before accepting traffic:

- Confirm `/` loads `index.php` and `/dashboard` resolves to `dashboard.php`.
- Confirm direct requests to `install/`, `config/`, and `vendor/` return 403 or
  404.
- Confirm direct requests to `config/config.settings.php`, `composer.json`, and
  `composer.lock` return 403 or 404 and never render file contents.
- Confirm directory browsing is disabled for the whole site and for mutable
  public paths.

## Sensitive Paths

`install/` contains the installer and SQL assets. After installation, remove or
block the directory. In operational runbooks, keep the exact checklist item
`remove or block install/` before the site is reachable from the internet. If
the directory must remain on disk for upgrade planning, keep it inaccessible
through the web server and allow access only during an authenticated maintenance
window.

`config/` contains `config/config.settings.php`, which stores database
credentials, application URL/path settings, and `CRYPTED_KEY`. The web server
must never serve `config/` or `config/config.settings.php` directly. The file
should be readable by PHP but not writable at runtime.

`vendor/` contains third-party Composer packages, metadata, tests, examples, and
autoload files. PHP includes `vendor/autoload.php` server-side, but browsers do
not need direct access to `vendor/`. Block direct requests to `vendor/` to reduce
dependency fingerprinting and accidental exposure of package tests or examples.

Repository metadata and operational files such as `composer.json`,
`composer.lock`, `README.md`, `.git/`, `.github/`, `docs/`, `tests/`,
`examples/`, `experiments/`, and `scripts/` should not be published as browsable
web content. Keep them outside public routing where possible, or block them with
server rules when the application root is the document root.

## Install-Time Permissions

The installer checks that these paths are writable during installation:

- `config/config.settings.php`
- `public/`

Set those permissions only for the installation window. A typical Linux
deployment can make the files owned by the deployment user and group-writable by
the PHP-FPM or Apache user for the duration of installation, for example `0640`
or `0660` for `config/config.settings.php` and `0750` or `0770` for `public/`.
Avoid world-writable `0777` permissions on production hosts.

After the installer writes configuration:

- Remove write permission from `config/config.settings.php`; keep it readable by
  the PHP runtime only. Treat it as read-only after installation.
- Remove or block install/ before production traffic reaches the site.
- Pre-create the runtime upload directories below with the PHP runtime as the
  only writer.

## Runtime Permissions

At runtime, keep application code, `config/`, `install/`, `vendor/`, `assets/`,
`docs/`, `tests/`, `scripts/`, `examples/`, and `experiments/` read-only for the
PHP runtime unless a controlled deployment step is replacing files.

The PHP runtime needs write access only where the application publishes mutable
files:

- `public/user` for profile pictures.
- `public/logo` for admin-managed logos.
- `public/chat` for chat attachments.
- `public/identity` for identity documents and camera images.
- `public/proof` for payment proof uploads.
- `public/bank-proof` for bank-transfer proof uploads.
- `public/qrcode` for generated payment QR images.

Pre-create those directories with restrictive ownership and permissions. Use a
dedicated PHP runtime user, disable directory listing, and keep upload execution
guards active for every mutable public directory. The application cache used by
current code is database-backed, so there is no separate filesystem cache
directory to make writable unless a local deployment adds one.

Keep valid static upload reads working only where the application expects them.
Use [docs/upload-storage-deployment.md](upload-storage-deployment.md) for the
Apache, Nginx, IIS, and reverse proxy rules that prevent uploaded PHP-like files
from executing below `public/*`.

## Apache

The repository includes a root `.htaccess` for `/dashboard` and a
`public/.htaccess` upload guard. Production Apache deployments should also deny
sensitive paths at the vhost level. If `.htaccess` is allowed, the same rules
can be translated into the application-root `.htaccess`.

```apache
<Directory "/var/www/krypto">
    Options -Indexes
    AllowOverride All
</Directory>

<LocationMatch "^/(?:install|config|vendor)(?:/|$)">
    Require all denied
</LocationMatch>

<LocationMatch "^/(?:composer\.(?:json|lock)|README\.md|\.git|\.github|docs|tests|scripts|examples|experiments)(?:/|$)">
    Require all denied
</LocationMatch>
```

If `AllowOverride None` is used, copy the repository rewrite and upload-deny
rules into the vhost instead of relying on `.htaccess`.

## Nginx

Place deny locations before the generic PHP handler and before broad static file
locations.

```nginx
root /var/www/krypto;
index index.php;

location = /dashboard {
    rewrite ^ /dashboard.php last;
}

location ~ ^/(?:install|config|vendor)(?:/|$) {
    deny all;
    return 404;
}

location ~ ^/(?:composer\.(?:json|lock)|README\.md|\.git|\.github|docs|tests|scripts|examples|experiments)(?:/|$) {
    deny all;
    return 404;
}

location / {
    autoindex off;
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php(?:/|$) {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Add the upload locations from
[docs/upload-storage-deployment.md](upload-storage-deployment.md) before the
generic PHP handler as well.

## IIS

Use Request Filtering, URL Rewrite, and disabled directory browsing. Keep these
rules at the site level so they apply before PHP FastCGI receives the request.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <directoryBrowse enabled="false" />
    <security>
      <requestFiltering>
        <hiddenSegments>
          <add segment="install" />
          <add segment="config" />
          <add segment="vendor" />
          <add segment=".git" />
          <add segment=".github" />
          <add segment="docs" />
          <add segment="tests" />
          <add segment="scripts" />
          <add segment="examples" />
          <add segment="experiments" />
        </hiddenSegments>
      </requestFiltering>
    </security>
    <rewrite>
      <rules>
        <rule name="Block Krypto sensitive files" stopProcessing="true">
          <match url="^(composer\.(json|lock)|README\.md)$" />
          <action type="CustomResponse" statusCode="404" statusReason="Not Found" statusDescription="Not Found" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

Also apply the IIS upload guard from
[docs/upload-storage-deployment.md](upload-storage-deployment.md) to reject
PHP-like extensions in mutable public directories.

## Production Verification

Run the application checks after changing file permissions or web-server rules:

```bash
php scripts/run_tests.php
php scripts/lint_php.php
```

Then verify the deployed site with direct HTTP requests:

- `GET /install/` returns 403 or 404 after installation.
- `GET /config/config.settings.php` returns 403 or 404.
- `GET /vendor/composer/installed.json` returns 403 or 404.
- `GET /composer.json` and `GET /composer.lock` return 403 or 404.
- `GET /public/chat/example/payload.php` returns 403 or 404 and is not executed.
- `GET /public/user/example/avatar.jpg` returns a static file only when that URL
  is intentionally exposed by the application.
