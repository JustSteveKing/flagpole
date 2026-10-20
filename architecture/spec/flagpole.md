---
title: Flagpole
includes: [RFC-0001]
---

# Flagpole

Flagpole is a feature flag service built on Kit. An owner manages projects, environments and flags
through a JSON API under `/v1`. Applications evaluate flags with an OpenFeature SDK and its OFREP
provider, through `/ofrep/v1`.

- An owner is a Kit user. A project belongs to one owner.
- A project has environments, `development` and `production` to begin with, and flags.
- A flag has a type, named variants, and settings in every environment of its project.
- An SDK key belongs to one environment and can only evaluate its flags.
- Every change to a flag or its settings is recorded in the flag's history and sent to the owner's
  webhook subscriptions.

Flagpole runs locally. `php artisan db:seed` creates a demo owner, a project with a flag of each
type, and an SDK key for each environment. There is no web interface, no teams, no event streams
for evaluation, and no record of evaluations.

The pages that follow describe each part:

- [Architecture](architecture.md)
- [Projects and environments](projects-and-environments.md)
- [Flags and settings](flags-and-settings.md)
- [Evaluation](evaluation.md)
- [SDK keys](sdk-keys.md)
- [History and webhooks](history-and-webhooks.md)
