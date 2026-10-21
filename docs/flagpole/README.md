# Flagpole documentation

How to run Flagpole and use it. If you are new, start with [Getting started](getting-started.md);
it has the demo running and a flag changing in a few minutes.

- [Getting started](getting-started.md): install, seed the demo, evaluate the flags, change one
- [Managing flags](managing-flags.md): projects, environments, flags, archiving, history, and keys
  for scripts and CI
- [Targeting](targeting.md): rules, operators, percentage rollouts, and what each reason means
- [Evaluating flags](evaluating.md): SDK keys, the OpenFeature providers, polling, and the OFREP
  endpoints
- [Webhooks](webhooks.md): the events every change sends, and what they leave out

## Elsewhere

- **The API reference** is generated from the code and checked against real responses. It is at
  `http://127.0.0.1:8000/docs/index.html` while Flagpole runs, and in
  [`public/docs/openapi.yaml`](../../public/docs/openapi.yaml).
- **[Kit's guides](../README.md)** cover what Flagpole is built on: authentication, errors, the HTTP
  standards, deployment and CI. They apply here unchanged.
- **[The design record](../../architecture/INDEX.md)** says why Flagpole works the way it does: an
  RFC for each area and an ADR for each decision, with the alternatives they beat. The spec pages
  in it describe the system as built, more tersely than these guides.
