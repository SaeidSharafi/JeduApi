# Single subscriber for campaign event dispatch

Campaigns are triggered by several domain events and by scheduled conditions. We dispatch every event-driven campaign type through **one** Laravel event subscriber that maps each domain event to a campaign type, plus **one** scheduled command for condition-based sweeps (birthday, seasonal) — rather than one listener per event.

**Considered Options**: (1) one listener per campaign type; (2) a registry/DB mapping table. Chose the single subscriber because the event → type mapping is small and closed; a registry would be YAGNI until the mapping grows beyond a handful of pairs.
