---
paths:
  - 'tests/Feature/Api/V1/Shop/Sale/**'
---

# Sale

## Keep Queue::fake provisioning list in sync with OrderStatusUpdateListener
phpunit.xml sets QUEUE_CONNECTION=sync, so any provisioning job missing from Queue::fake runs synchronously and hits the real Moodle/BBB/Skyroom/IMS endpoint -> intermittent 503 (RecoverableProvisioningException, mapped in bootstrap/app.php). The canonical provisioning path is a single job: `ProvisionEnrollmentProviderJob` (dispatched by `OrderStatusUpdateListener` per planned provider via `ProvisioningAttemptService`). The fake list in these checkout tests MUST include `ProvisionEnrollmentProviderJob::class`. When adding a new job to the listener's dispatch path, add it to these fakes too.
