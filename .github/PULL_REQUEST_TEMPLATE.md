## What and why

<!-- What this changes, and why. Link the issue it closes. -->

## Checklist

- [ ] `composer lint`, `composer rector:check` and `composer stan` pass
- [ ] `php artisan test` passes, with a test that fails without this change
- [ ] `legible check public/docs/openapi.yaml` passes
- [ ] `archdoc lint`, `archdoc index --check` and `archdoc glossary --check` pass
- [ ] Behaviour changes are reflected in the spec page in `architecture/spec/`
- [ ] `public/docs/` and `.scribe/` are committed only if an endpoint or its docs changed
- [ ] Changes to Kit's own files are in a commit of their own
