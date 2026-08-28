---
paths:
  - 'tests/Feature/Api/V1/Shop/Sale/**'
---

# Sale

## Keep Queue::fake provisioning list in sync with OrderStatusUpdateListener
phpunit.xml sets QUEUE_CONNECTION=sync, so any provisioning job missing from Queue::fake runs synchronously and hits the real Moodle/BBB/Skyroom/IMS endpoint -> intermittent 503 (RecoverableProvisioningException, mapped in bootstrap/app.php). The fake list in these checkout tests MUST cover every job OrderStatusUpdateListener can dispatch: ProvisionImsEnrollmentJob, ProvisionEnrollmentProviderJob, ProvisionMoodleQuizJob, ProvisionSkyroomEnrollmentJob, ProvisionSpotPlayerEnrollmentJob, ProvisionBbbEnrollmentJob. When adding a new provisioning job to the listener, add it to these fakes too. Legacy ProvisionMoodleEnrollmentJob is dead code - do not re-add.
