# Organization training requests are separate from collaboration requests

The Organization page’s public form creates an Organization Training Request rather than a Collaboration Request. It captures contact and organization details, manually entered requested course names, optional notes, and an optional PDF upload limited to 2 MB; it does not select existing shop courses. The request retains its own inbound-request lifecycle while using the shared status vocabulary for staff follow-up, and snapshots the page’s linked Vendor at submission time. A submission is accepted only when its request data and optional attachment are stored successfully, so a failed attachment must not leave an incomplete request. Submission creates a database notification for every staff member authorized to access these requests, using the existing staff-notification payload shape (`title`, `message`, `resource_type`, and `resource_id`) with `organization_training_request` as the resource type.

## Considered Options

Reusing Collaboration Request was rejected because this form represents a structured educational-needs request with different data and attachment behavior, not a general collaboration proposal.
