# Server-side files that are not part of the application

## `public_html.htaccess`

Belongs at `~/domains/will.skillleo.com/public_html/.htaccess` on the server.

It existed **only on the server** until 26 August 2026, which meant a rebuilt
host would have come back with no PHP 8.4 selector, no document-root guard and
no rewrite — a broken site serving its own source. It is tracked here so it can
be restored, and so a change to it is reviewable like any other change.

It is deliberately **not** at the repository root: this directory is not a
document root locally, and a stray `.htaccess` there would do nothing but
confuse.

Three things it does, in order, and the order matters:

1. **Selects PHP 8.4.** The domain default is 8.3, which fails Composer's
   platform check before Laravel boots. `AddType` is the only form this host
   honours; `AddHandler` is accepted and silently ignored.
2. **Denies source, config and dependency directories.** Defence in depth —
   the rewrite below already makes them unreachable, but this holds if
   mod_rewrite is ever unavailable.
3. **Rewrites everything into `public/`.**

`storage` is deliberately absent from the blanket deny list. `/storage/...` is
also the public disk path: the rewrite turns it into `public/storage/...`, a
symlink to `storage/app/public`, which holds only files meant to be served.
The real Laravel storage subdirectories are denied by name instead. Denying
`/storage/` outright — as it did until 26 August — makes every uploaded file
on the site return 404.
