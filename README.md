# Next Chapter Collective

Website source and custom PHP admin console. This repository copy excludes the owner account, encryption key, setup-key configuration, private drafts, revisions, sessions, enquiry configuration and uploaded media.

## Hosting

Use PHP 8.1+ with OpenSSL, DOM, Fileinfo and sessions. Apache/LiteSpeed must honor the supplied .htaccess rules. GitHub Pages cannot run the PHP admin console or send enquiries; use Hostinger for the complete site.

For the already-configured website, deploy the separate private Hostinger ZIP supplied by Codex. Keep that ZIP off GitHub. When updating code from this repository, preserve settings.php, admin/install-key.php, admin/private/ and assets/uploads/ on the server. Back up account.php and encryption-key.php together.

For a fresh installation, copy settings.example.php to settings.php. Generate a long random setup key privately, compute its SHA-256 hash, copy admin/install-key.example.php to admin/install-key.php and replace the placeholder with that hash. Keep the original setup key private. Visit /admin/ over HTTPS and use that setup key to create an owner account. Do not commit these configuration files or generated private data.

## Editing

Open /admin/ on the PHP host for pages, rich text, sections, images, menus, headers, footers and hero features. Save drafts before publishing. The browser-only /admin/demo.html preview cannot publish or upload.

## Repository uploads

Upload the contents of this folder to the repository root. Do not upload this folder as a single enclosing directory or commit the ZIP itself. Keep the repository private unless you intend to publish the website source.
