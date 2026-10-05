# Saradhi Member Module

The Member module owns organization membership, member profiles, membership types, relationships, and the membership application workflow.

## Membership application workflow

A submitted application is kept separate from an active member record:

`Draft → Submitted → Verified → Reviewed → Approved → Payment → Confirmed`

Rejection can occur during verification, review, or approval. A rejection may permit revision and re-application; the decision is recorded in the application status history.

A `Member` and `Membership` are created only during final confirmation. The resulting membership is activated after confirmation.

Application data is stored as a submission snapshot. Identification documents remain attached to the application until confirmation, then are transferred to the user's identification record.

## Authorization

Membership application actions are exposed as atomic permissions:

- `membership.application.view`
- `membership.application.submit`
- `membership.application.verify`
- `membership.application.review`
- `membership.application.approve`
- `membership.application.receive-payment`
- `membership.application.confirm`

Permissions are granted through Core roles/designations. `MembershipApplication` participates in Core authorization context so unit-scoped assignments can be evaluated against the application's selected unit.

## Module extensions

Optional enabled modules can contribute application fields and validation through `MembershipApplicationExtension`. Extension data is persisted only when the application is finally converted into a member.

## Dependencies

- Core
- Laravel
- Spatie Media Library
