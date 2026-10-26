# Member Unit Transfer API

All endpoints require authentication, organization resolution/access, and the Member module to be enabled. The `{organization}` route parameter is the organization ID. Transfer records are always queried within that organization.

Base path: `/api/v1/organizations/{organization}/member-unit-transfers`

| Method | Path | Purpose | Authorization |
|---|---|---|---|
| GET | `/` | List transfers visible through source- or target-unit context | `member.unit-transfer.view` |
| POST | `/` | Submit a transfer | `member.unit-transfer.submit` in source-unit context |
| GET | `/{transfer}` | View a transfer | `member.unit-transfer.view` in source or target unit context |
| POST | `/{transfer}/approvals/{side}/approve` | Approve `outgoing` or `incoming` side | `member.unit-transfer.approve` in that approval's unit context |
| POST | `/{transfer}/reject` | Reject a side | `member.unit-transfer.reject` in the selected approval's unit context |
| POST | `/{transfer}/complete` | Complete after both approvals | `member.unit-transfer.complete` in source or target unit context |

## Submit payload

```json
{
  "member_id": 123,
  "to_unit_id": 456,
  "spouse_included": true
}
```

`spouse_included` is optional and defaults to `true`. The server validates organization/unit ownership and eligible spouse conditions.

## Approve

`POST .../{transfer}/approvals/outgoing/approve` or `incoming/approve` with no body.

## Reject payload

```json
{
  "side": "outgoing",
  "reason": "Reason for rejection"
}
```

## Response and errors

Successful responses use a `data` key. Submission returns HTTP 201; other successful operations return HTTP 200. Cross-organization or nonexistent transfer IDs return 404. Authorization failures return 403. Domain/workflow conflicts and invalid transfer states return 422.
