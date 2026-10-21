# OpenFeature check

Flagpole has no SDK of its own: applications use OpenFeature's, over OFREP. This runs
OpenFeature's own OFREP providers against the seeded demo and checks every answer against the
demo's rules, so you can see that for yourself rather than take the main README's word for it.

With Flagpole running and seeded as the [main README](../../README.md) describes:

```bash
cd examples/openfeature
npm install
SDK_KEY=<development SDK key> npm run check
```

`FLAGPOLE_URL` points it somewhere other than `http://127.0.0.1:8000`. It needs Node 22 or later.

The server provider (`@openfeature/ofrep-provider` with `@openfeature/server-sdk`) evaluates
each flag on its own: a flag of each type matched by a rule, one switched off, a rollout with no
`targetingKey`, a flag that does not exist, and a boolean asked for as a string. The web provider
(`@openfeature/ofrep-web-provider` with `@openfeature/web-sdk`) evaluates them all in one bulk
request and then polls; polls while nothing has changed must get `304`.

Then it signs in as the demo owner and switches `new-checkout` off in development, through the
management API with the settings' `ETag` in `If-Match`, and waits for the web provider's next poll
to notice. It switches the flag back on at the end, so it can be run again. It exits non-zero if
anything did not match.

The provider versions are pinned exactly, in `package.json` and `package-lock.json`, because
they are what was checked.
