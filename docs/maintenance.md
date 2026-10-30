# Maintenance

Keep plugin upgrades separate from customer business data and mail settings.
Take the shop's normal rollback precautions. Install the new Composer version,
run `plugin:refresh`, `plugin:update SkyyStarcode`, `assets:install` and
`cache:clear`, then test each enabled form and its Store API equivalent.

Configuration changes affect server checks immediately. Purge any separate CDN
HTML cache as well so the browser receives the matching form configuration.
Exclude the challenge routes and their compatibility aliases from caching.
They must retain `no-store`.

The proof database must be shared by all nodes serving the shop. Application
secrets must match across those nodes. Rotating `APP_SECRET` invalidates pending
proofs; visitors can obtain a new one. Database failure returns HTTP 503 instead
of accepting an unchecked protected submission.

For a restrictive Content Security Policy, allow the shop origin for scripts,
styles, workers and connections. There are no vendor CDN requirements.
Use HTTPS. Browsers must support Web Crypto, Web Workers, custom elements and
modules. JavaScript-disabled visitors cannot complete protected submissions.
Keep an alternative contact route and test assistive technology with the actual
shop theme. Keyboard and responsive checks are not a full accessibility audit.

## Upgrade from Starcode 0.1

Easy Captcha 0.2 replaces `skyyware/starcode` 0.1 without creating a second
Shopware plugin. Back up the shop under its normal maintenance procedure and
run these commands from the shop root:

```sh
composer remove skyyware/starcode --no-update
composer config --unset repositories.starcode
composer config repositories.easy-captcha vcs https://github.com/skyyware/easy-captcha
composer require skyyware/easy-captcha:^0.2 --with-dependencies
php bin/console plugin:refresh
php bin/console plugin:update SkyyStarcode
php bin/console assets:install
php bin/console cache:clear
```

The `--no-update` step changes the root requirement without removing installed
files. The subsequent Composer transaction replaces the old package. If the
old VCS repository uses a different key, replace that entry instead. Review
the Composer diff before deploying. Do not run `plugin:uninstall` or install a
second copy under `custom/plugins`.

Shopware identifies this plugin by `Skyyware\Starcode\SkyyStarcode`, not its
display label. That class, `SkyyStarcode.config.*`, migration identity and
`skyy_starcode_*` tables stay unchanged. The same plugin ID, activation state,
configuration and replay records must remain after the update. Pending proofs
remain valid until their original expiry if `APP_SECRET` is unchanged.

The public API is `window.EasyCaptcha`. `window.Starcode` remains an alias to
the same object. Both old challenge URLs still work, as do the `starcode` proof
field, `X-Starcode-Proof` header and `STARCODE_*` error codes. Existing asset,
Twig, CSS and data-attribute identifiers remain compatibility names. The asset
cache version changes to 0.2.0 so cached pages do not keep the old JavaScript.

The Composer `replace` declaration covers the old 0.1 series, not arbitrary
future versions. A plain `composer update skyyware/starcode` does not migrate
the root requirement. The package rename needs the explicit switch above.

## Bounded data cleanup

Expired entries are not valid proofs. While the plugin is active, an operator
can inspect and remove expired rows without waiting for visitor traffic:

```sh
php bin/console easy-captcha:cleanup --dry-run
php bin/console easy-captcha:cleanup
```

The dry run reports a count without changing rows. Cleanup uses one cutoff,
deletes at most 1,000 rows per table per batch, and stops after 100 batches.
Rows that have not expired at the cutoff stay unchanged. If expired rows remain,
the command exits with status 1 and asks you to run it again. A repeated run
with no expired rows succeeds without deleting anything.
The original `starcode:cleanup` command remains an alias.

The command does not schedule itself. The five-minute proof validity and
two-minute rate expiry are not physical deletion deadlines. A physical
retention deadline needs an approved invocation policy and verified execution.

When the plugin is disabled, its console command is unavailable. An operator
can use bounded SQL under the shop's maintenance policy instead:

```sql
DELETE FROM skyy_starcode_proof WHERE expires_at <= UNIX_TIMESTAMP() LIMIT 1000;
DELETE FROM skyy_starcode_rate WHERE expires_at <= UNIX_TIMESTAMP() LIMIT 1000;
```

Repeat only as needed under the shop's maintenance policy. No customer data,
messages or Shopware queues are involved. After uninstalling the plugin and
confirming that no node still uses it, its two tables may be removed under the
operator's normal cleanup approval. Easy Captcha does not drop them automatically.

## Release checks

Run PHP and JavaScript tests, syntax validation and Composer/npm audits. Build
assets with the committed npm lockfile and compare the generated files. Retain
the ALTCHA and Svelte license notices. Verify native configuration schema and
the supported Shopware/PHP combination in an isolated shop.

Check a valid request, a missing proof, expiry, replay, changed form actions,
failed challenge requests, reload/history restoration, form reset, keyboard
operation and narrow screens. In the native CMS contact form, verify the
checkbox before submitting and confirm that the first click sends exactly one
POST. Also test submission without checking the box first. For a dynamic form,
await `EasyCaptcha.protect(form)` before each of two sequential AJAX attempts and
create a new `FormData` each time. Each attempt must use a different proof.
Repeat with the `Starcode` alias. Distinguish proof rejection from Shopware's
native contact rate limit.
Confirm that
checkout and unsubscribe are untouched. Verify that no external requests or
real messages occur. Test custom adapters separately; the standard widget cannot
prove that a third-party submission script uses the integration correctly.

No repository automation is configured. Run release checks manually. A Git tag
is not evidence of customer acceptance or permission to deploy to production.
