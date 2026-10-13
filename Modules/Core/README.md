# Saflivo Core

Core infrastructure for organizations and units. Domain-specific member and finance functionality lives in the Member and Finance modules.

## Notification channel architecture

WhatsApp and SMS support two credential scopes:

1. **System default channel** — configured by a system administrator.
2. **Organization channel** — configured by the organization's Super Administrator.

An organization stores `use_system_default` independently from its own channel activation. The effective channel is resolved as follows:

- An enabled organization channel overrides the system default.
- If the organization channel is disabled or absent, the system default is used only when `use_system_default` is enabled for that organization and the system channel is enabled.
- User notification preferences are applied after channel resolution.
- Channel credentials are encrypted at rest and are never returned by the API; responses expose only `has_credentials`.

Membership reminder jobs such as subscription due/overdue and passport/identification expiry are owned by the Member domain and should call the Core notification service. They should run as scheduled jobs rather than being coupled to HTTP requests.
