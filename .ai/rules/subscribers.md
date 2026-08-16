---
paths:
  - 'app/Subscribers/**'
---

# Subscribers

## Campaign event dispatch uses one explicit subscriber
Wallet campaign events map through a single CampaignEventSubscriber (app/Subscribers/), registered explicitly via Event::subscribe in EventServiceProvider::boot() — the codebase's only subscriber. Everything else stays auto-discovered one-listener-per-event (app/Listeners). Add new campaign event→type mappings in the subscriber's subscribe() map, never as separate listeners. Domain events live in app/Events/ (Dispatchable, +SerializesModels when carrying models).
