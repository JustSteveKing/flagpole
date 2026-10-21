# Security

## Reporting a vulnerability

Please do not open a public issue. Report it privately through
[GitHub's security advisories](https://github.com/JustSteveKing/flagpole/security/advisories/new)
for this repository, with what you found, how to reproduce it, and what you think the impact is.

If it is confirmed, the fix and an advisory are published together, and you are credited unless
you would rather not be.

## What counts

Flagpole is an example application that runs locally, with no hosted instance. Reports are still
valuable, because people copy what an example does. In scope:

- Anything that lets an SDK key do more than evaluate its own environment's flags
- Anything that lets one owner read or change another owner's projects
- Ways around `If-Match`, idempotency or ability checks
- Targeting data, such as rules, reaching somewhere it should not, like a webhook payload

Not a vulnerability:

- The demo owner's password, `password`, and the demo SDK keys. The seeder refuses to run in
  production.
- Settings in `.env.example` that are relaxed for local development, such as allowing webhooks to
  plain `http` addresses.

## Kit and api-toolbox

If the problem is in [Kit](https://github.com/JustSteveKing/kit) or
[api-toolbox](https://github.com/JustSteveKing/api-toolbox) rather than in Flagpole's own code,
report it to that repository's security advisories instead, since it affects every application
built on them. Not sure which? Report it here and it will be passed on.

## Supported versions

Only the latest release gets fixes.
