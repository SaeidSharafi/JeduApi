# Device fingerprinting is a server-side IP + User-Agent hash

To throttle account-registration velocity we needed a device signal. We decided to fingerprint devices server-side as `sha256(ip + user_agent)`, stored in a `user_devices` table, rather than using client-side JS fingerprinting.

The API is headless (no guaranteed browser/frontend), so client-side fingerprinting isn't always available; the server-side hash works for every request and avoids the privacy and circumvention problems of JS fingerprints. It is less precise than a JS fingerprint, which is acceptable because we only use it for coarse velocity caps (3 registrations/day per IP and per device-hash).
