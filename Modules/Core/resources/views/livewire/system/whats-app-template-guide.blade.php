<div>
    <div class="page-head">
        <div>
            <h1>WhatsApp template guide</h1>
            <div class="muted">Saflivo system-admin reference for Meta WhatsApp Cloud API message templates.</div>
        </div>
    </div>

    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'templates'])
        <div class="long-page-content">
    <section class="card">
        <h2>How Saflivo currently sends WhatsApp notifications</h2>
        <p>Saflivo sends WhatsApp messages using Meta-approved template messages. The current provider integration uses one configured template per WhatsApp channel. Its body must contain exactly two positional placeholders:</p>
        <ul>
            <li><code>@{{1}}</code> — notification title</li>
            <li><code>@{{2}}</code> — notification body</li>
        </ul>
        <p class="muted">The template name and language configured in Saflivo must exactly match the approved template name and language in the WhatsApp Business Account (WABA). A template must be approved and active before it can be used.</p>
    </section>

    <section class="card">
        <h2>Template to submit for the current integration</h2>
        <p><strong>Suggested template name:</strong> <code>saflivo_notification_update</code></p>
        <p><strong>Category:</strong> Utility</p>
        <p><strong>Language:</strong> English (US) — <code>en_US</code> (submit other languages separately if required).</p>
        <p><strong>Header:</strong> None</p>
        <p><strong>Body:</strong></p>
        <pre style="white-space:pre-wrap; overflow-wrap:anywhere; padding:14px; border:1px solid var(--line); border-radius:8px; background:var(--canvas);">@{{1}}

@{{2}}

This is an automated service notification from Saflivo.</pre>
        <p><strong>Sample values for Meta review:</strong></p>
        <ul>
            <li><code>@{{1}}</code>: Membership application submitted</li>
            <li><code>@{{2}}</code>: Your membership application has been submitted and is under review. We will notify you when its status changes.</li>
        </ul>
        <p class="muted">This is a proposed template compatible with the current two-parameter implementation, not a guarantee of approval. Meta reviews templates and may request edits or classify the template differently. If rejected, follow the reason shown in WhatsApp Manager and avoid changing the business meaning just to bypass review.</p>
    </section>

    <section class="card">
        <h2>Usage for applicants and organization staff</h2>
        <p><strong>A separate admin template is not technically required by the current integration.</strong> The same approved template can be used for applicant notifications and staff notifications because Saflivo supplies the notification title as <code>@{{1}}</code> and the notification body as <code>@{{2}}</code>. The text changes for each notification; the template structure stays the same.</p>
        <p><strong>Applicant example values:</strong></p>
        <ul>
            <li><code>@{{1}}</code>: Membership application submitted</li>
            <li><code>@{{2}}</code>: Your membership application has been submitted and is under review. We will notify you when its status changes.</li>
        </ul>
        <p><strong>Authorized verifier example values:</strong></p>
        <ul>
            <li><code>@{{1}}</code>: New membership application requires verification</li>
            <li><code>@{{2}}</code>: A new membership application has been submitted to your organization. Sign in to Saflivo to review and verify the application.</li>
        </ul>
        <p>Use the same template for other staff actions too, such as review, approval, payment collection and final confirmation. Notifications must only be sent to staff authorized for the relevant stage. Do not include full applicant identity details or sensitive documents in the WhatsApp message; direct staff to sign in to Saflivo.</p>
        <p>If business owners later want more tailored wording, we can add dedicated templates. That requires a future template-to-event mapping enhancement in Saflivo; the current implementation uses one configured template name per channel.</p>
    </section>

    <section class="card">
        <h2>Saflivo default channel vs organization-specific channel</h2>
        <p>The same template body and name can be used by both channel types <strong>only when that template has been approved in the WhatsApp Business Account (WABA) associated with each channel's credentials</strong>. Templates belong to a WABA, not merely to Saflivo's application.</p>
        <ul>
            <li><strong>Saflivo default channel:</strong> create and get the template approved in Saflivo's WABA, then configure that approved template name and language with Saflivo's system-level Phone Number ID and access token.</li>
            <li><strong>Organization-specific channel:</strong> if the organization uses its own WABA, its administrator must ensure the same template is created and approved in that organization's WABA, then configure that organization's Phone Number ID, access token, template name and language.</li>
            <li>Template names are not automatically shared or copied between WABAs. Approval in Saflivo's WABA does not approve the template for an organization's separate WABA.</li>
            <li>If an organization has its own valid credentials, Saflivo selects that channel first. The system default is used only if the organization's WhatsApp feature permission and default-channel permission are both enabled and the default channel is configured.</li>
        </ul>
        <p class="muted">The current Core provider adapter supports both sources using the same Meta Cloud API implementation. A real delivery still requires valid credentials, an approved/active template in the selected WABA, a valid sender Phone Number ID, an eligible recipient who has opted in, and a running queue worker.</p>
    </section>

    <section class="card">
        <h2>Meta submission checklist</h2>
        <ol>
            <li>Open Meta WhatsApp Manager and select the correct WhatsApp Business Account.</li>
            <li>Open Message templates and create a new template.</li>
            <li>Use a descriptive lowercase name with underscores: <code>saflivo_notification_update</code>.</li>
            <li>Select <strong>Utility</strong> for account, application, payment and service-status notifications. Meta makes the final classification.</li>
            <li>Choose the language that will be configured in Saflivo.</li>
            <li>Paste the body above and provide realistic sample values for every placeholder.</li>
            <li>Submit for review and wait for the template status to become approved/active.</li>
            <li>Configure the approved template name and language in Saflivo's WhatsApp channel settings.</li>
            <li>Test with a recipient who has opted in to receive WhatsApp messages. Verify the recipient number is stored in international format.</li>
        </ol>
        <p>Meta's official Cloud API documentation and request examples:</p>
        <ul>
            <li><a href="https://developers.facebook.com/docs/whatsapp/cloud-api/" target="_blank" rel="noopener">WhatsApp Cloud API documentation</a></li>
            <li><a href="https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api" target="_blank" rel="noopener">Meta's official WhatsApp Cloud API Postman collection</a></li>
        </ul>
    </section>

    <section class="card">
        <h2>Credentials and configuration</h2>
        <ul>
            <li><strong>Phone Number ID:</strong> Meta's ID for the business phone number that sends messages; it is not the visible phone number itself.</li>
            <li><strong>Access token:</strong> a valid access token with the required WhatsApp messaging permission. Keep it secret and rotate it according to the organization's security policy.</li>
            <li><strong>Template name:</strong> exact approved template name, for example <code>saflivo_notification_update</code>.</li>
            <li><strong>Template language:</strong> exact language code, for example <code>en_US</code>.</li>
            <li><strong>API version:</strong> a supported Graph API version configured in Saflivo.</li>
        </ul>
        <p>Saflivo's default channel credentials are managed by the System Admin. Organization credentials may be used only when WhatsApp is allowed for that organization. An organization's own valid channel takes precedence; Saflivo's default is used only when both the WhatsApp feature and default-channel permissions are enabled for that organization.</p>
        <p><strong>Never paste access tokens or secrets into this guide, support tickets, screenshots, or template samples.</strong></p>
    </section>

    <section class="card">
        <h2>End-to-end implementation checklist</h2>
        <p>Use this checklist when enabling Meta WhatsApp Cloud API for the first time, onboarding a new organization, or troubleshooting a later setup. Saflivo uses the same provider adapter for the system default channel and organization-specific channels; the selected channel supplies its own credentials and template settings.</p>
        <ol>
            <li><strong>Prepare the Meta business setup.</strong> In Meta Business Portfolio, create or select the WhatsApp Business Account (WABA), add/verify the sending phone number, and complete any business verification or display-name requirements Meta requests.</li>
            <li><strong>Get API values.</strong> Obtain the sender's <code>phone_number_id</code> and an access token with the WhatsApp messaging permission. For production, use an appropriately managed system-user token and keep it secret. The visible phone number is not the Phone Number ID.</li>
            <li><strong>Create and approve the template.</strong> Create <code>saflivo_notification_update</code> in WhatsApp Manager with the two body placeholders described above. Wait until its status is approved/active. Record the exact template name and language code.</li>
            <li><strong>Configure Saflivo's default channel (System Admin).</strong> Save the system credentials and enable the system WhatsApp channel using the system endpoint below. Do this once for Saflivo's own WABA.</li>
            <li><strong>Enable WhatsApp per organization (System Admin).</strong> After creating the organization, set <code>is_allowed</code> and <code>use_system_default</code> separately for that organization. These are separate permissions; neither should be inferred from the other.</li>
            <li><strong>Choose the organization's channel.</strong> If the organization will use Saflivo's default channel, no organization credentials are needed. If it will use its own WABA, its Super Administrator configures its own Phone Number ID, access token, approved template name and language.</li>
            <li><strong>Run a real delivery test.</strong> Use a recipient who has opted in, ensure the recipient phone is in international format, run the Laravel queue worker, trigger a real notification, and inspect the notification delivery record and Meta response.</li>
        </ol>
        <p class="muted">The System Admin guide is documentation only; it does not create Meta credentials or submit templates to Meta. The channel settings are saved through Saflivo's protected API endpoints. If an organization-creation UI is added later, it should expose the same two permission flags and call the same backend rules.</p>
    </section>

    <section class="card">
        <h2>Configure Saflivo's default WhatsApp channel</h2>
        <p>Do this as a System Admin after the template has been approved in Saflivo's WABA. The endpoint is authenticated and restricted to the System Admin role.</p>
        <p><strong>Request:</strong> <code>PUT /api/v1/system/notification-channels/whatsapp</code></p>
        <pre style="white-space:pre-wrap; overflow-wrap:anywhere; padding:14px; border:1px solid var(--line); border-radius:8px; background:var(--canvas);">{
  "is_enabled": true,
  "credentials": {
    "phone_number_id": "META_PHONE_NUMBER_ID",
    "access_token": "META_ACCESS_TOKEN",
    "template_name": "saflivo_notification_update",
    "template_language": "en_US",
    "api_version": "v23.0"
  }
}</pre>
        <p>Replace every example value with the actual value from Meta. Send credentials only over HTTPS and do not commit them to source control. Saflivo encrypts stored channel credentials and returns a credential-presence indicator rather than the secret.</p>
        <p>To check configuration without reading the secret, call <code>GET /api/v1/system/notification-channels/whatsapp</code>. A configured channel should report <code>is_enabled: true</code> and <code>has_credentials: true</code>.</p>
    </section>

    <section class="card">
        <h2>When creating a new organization</h2>
        <p>WhatsApp permissions are configured <strong>per organization</strong>, after the organization record exists. Creating an organization does not by itself grant permission to send WhatsApp messages.</p>
        <ol>
            <li>Create the organization through the System Admin organization page/API as usual.</li>
            <li>Decide whether the organization may use WhatsApp at all. If not, set <code>is_allowed</code> to <code>false</code>; neither its own credentials nor Saflivo's default channel may be used.</li>
            <li>If WhatsApp is allowed, separately decide whether it may use Saflivo's default channel. Set <code>use_system_default</code> to <code>true</code> only if the organization is allowed to fall back to Saflivo's configured default.</li>
            <li>If the organization will use its own WhatsApp Business Account, the organization Super Administrator configures its channel after signing in to that organization's tenant.</li>
            <li>If the organization will use only Saflivo's default channel, leave organization credentials unset. The organization needs both permission flags set to <code>true</code>, and Saflivo's default channel must be enabled and configured.</li>
        </ol>
        <p><strong>Set the permissions:</strong> <code>PUT /api/v1/system/organizations/{organization}/notification-channels/whatsapp/permissions</code></p>
        <pre style="white-space:pre-wrap; overflow-wrap:anywhere; padding:14px; border:1px solid var(--line); border-radius:8px; background:var(--canvas);">{
  "is_allowed": true,
  "use_system_default": true
}</pre>
        <p>Use <code>{"is_allowed": true, "use_system_default": false}</code> when the organization may use WhatsApp only with its own credentials. Use <code>{"is_allowed": false, "use_system_default": false}</code> to disable WhatsApp for the organization.</p>
        <p class="muted">Current implementation note: this workflow is available through the protected API. If the organization-creation UI does not yet show these controls, create the organization first and then call the permission endpoint; do not assume the permissions were automatically enabled.</p>
    </section>

    <section class="card">
        <h2>Configure an organization's own WhatsApp channel</h2>
        <p>Use this only when the System Admin has set <code>is_allowed: true</code> for that organization. Sign in as the organization's Super Administrator in the correct organization context.</p>
        <p><strong>Request:</strong> <code>PUT /api/v1/organization/notification-channels/whatsapp</code></p>
        <pre style="white-space:pre-wrap; overflow-wrap:anywhere; padding:14px; border:1px solid var(--line); border-radius:8px; background:var(--canvas);">{
  "is_enabled": true,
  "credentials": {
    "phone_number_id": "ORG_META_PHONE_NUMBER_ID",
    "access_token": "ORG_META_ACCESS_TOKEN",
    "template_name": "saflivo_notification_update",
    "template_language": "en_US",
    "api_version": "v23.0"
  }
}</pre>
        <p>The template values must refer to a template approved in the organization's own WABA. If its WABA is different from Saflivo's WABA, create and approve the same template separately in that WABA. Use the organization's own approved template name if it differs.</p>
        <p>Check status with <code>GET /api/v1/organization/notification-channels/whatsapp</code>. The response exposes <code>is_allowed</code>, <code>is_enabled</code>, <code>use_system_default</code>, <code>has_credentials</code> and <code>effective_source</code>, but never returns the secret.</p>
    </section>

    <section class="card">
        <h2>Channel selection rules</h2>
        <ol>
            <li>If the System Admin has not allowed WhatsApp for the organization, send no WhatsApp message.</li>
            <li>If WhatsApp is allowed and the organization has a usable, enabled organization-specific channel, use that channel first.</li>
            <li>If no usable organization channel exists, use Saflivo's default only when <code>is_allowed</code> and <code>use_system_default</code> are both true and the system default is enabled and configured.</li>
            <li>Otherwise, do not send WhatsApp. Other permitted channels, such as email, should continue independently.</li>
        </ol>
        <p>Example configurations:</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>WhatsApp allowed</th><th>Default allowed</th><th>Organization credentials</th><th>Expected channel</th></tr></thead>
                <tbody>
                    <tr><td>No</td><td>Either</td><td>Either</td><td>WhatsApp disabled</td></tr>
                    <tr><td>Yes</td><td>No</td><td>Valid</td><td>Organization-specific channel</td></tr>
                    <tr><td>Yes</td><td>No</td><td>Missing/invalid</td><td>No WhatsApp delivery</td></tr>
                    <tr><td>Yes</td><td>Yes</td><td>Valid</td><td>Organization-specific channel</td></tr>
                    <tr><td>Yes</td><td>Yes</td><td>Missing</td><td>Saflivo default, if configured</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Testing and troubleshooting</h2>
        <ol>
            <li>Check the channel status endpoint and confirm that it is enabled and reports credentials present. Secret values are intentionally not displayed.</li>
            <li>Confirm the Phone Number ID belongs to the WABA that owns the approved template, and that the token is valid and has WhatsApp messaging permission.</li>
            <li>Confirm the template name and language match exactly, and that the template is approved/active in the selected WABA.</li>
            <li>Confirm the recipient gave WhatsApp opt-in and that the phone number is stored with country code in international/E.164 format.</li>
            <li>Ensure Laravel's queue worker is running and inspect Saflivo's notification delivery record for the provider response or failure.</li>
            <li>Remember that an accepted send request is not proof of delivery. Delivery/read status requires a separately configured Meta webhook and webhook processing.</li>
        </ol>
        <p><strong>Common causes of failure:</strong> wrong Phone Number ID, expired/insufficient token, template not approved in the selected WABA, template name/language mismatch, recipient not eligible or opted in, disabled channel, missing organization permission, default fallback not allowed, or a stopped queue worker.</p>
        <p class="muted">Automated tests use mocked HTTP responses and do not send real WhatsApp messages. Always perform a real end-to-end delivery test after credentials and templates are configured.</p>
    </section>

    <section class="card">
        <h2>Future template catalogue</h2>
        <p>The current integration sends the configured generic template above. The templates below are a planning catalogue for future work. Dedicated templates will require extending Saflivo's provider configuration and notification-to-template mapping before they can be selected automatically.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Notification purpose</th><th>Suggested template name</th><th>Category</th><th>Example content / notes</th></tr></thead>
                <tbody>
                    <tr><td>Application submitted</td><td><code>membership_application_submitted</code></td><td>Utility</td><td>Confirm receipt and state that the application is under review.</td></tr>
                    <tr><td>Verification / review update</td><td><code>membership_application_status_update</code></td><td>Utility</td><td>Give the current status and the next expected step.</td></tr>
                    <tr><td>Approval and payment due</td><td><code>membership_payment_pending</code></td><td>Utility</td><td>State the amount, currency, due date and approved payment instructions where available.</td></tr>
                    <tr><td>Payment received</td><td><code>membership_payment_received</code></td><td>Utility</td><td>Confirm the payment amount and reference, without exposing unnecessary financial data.</td></tr>
                    <tr><td>Membership confirmed</td><td><code>membership_confirmed</code></td><td>Utility</td><td>Confirm membership and include the assigned membership number.</td></tr>
                    <tr><td>Correction required / rejected</td><td><code>membership_application_action_required</code></td><td>Utility</td><td>Explain that an action is required and direct the user to sign in for details. Avoid putting sensitive reasons in a lock-screen message.</td></tr>
                    <tr><td>Payment reminder</td><td><code>membership_payment_reminder</code></td><td>Utility</td><td>Identify the pending obligation, amount and due date.</td></tr>
                    <tr><td>Event registration / pass</td><td><code>event_registration_update</code></td><td>Utility</td><td>Confirm a requested registration or deliver a requested event update/pass.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="muted">Submit templates only when the related feature is implemented and the message is needed. Keep the examples factual, user-initiated or transaction-related where applicable, and provide representative sample values for each placeholder. Promotional messages must be evaluated separately and should not be sent through utility templates.</p>
    </section>


    <section class="card">
        <h2>WhatsApp login OTP — Authentication template</h2>
        <p>Login OTP uses a separate Meta Authentication template; do not reuse the Utility notification template for OTP. Suggested template name: <code>saflivo_login_otp</code>, category <strong>Authentication</strong>, language <code>en_US</code>, OTP copy-code button, and Meta's security recommendation enabled.</p>
        <p><strong>Template body preview:</strong></p>
        <pre style="white-space:pre-wrap; overflow-wrap:anywhere; padding:14px; border:1px solid var(--line); border-radius:8px; background:var(--canvas);">@{{1}} is your verification code. For your security, do not share this code.</pre>
        <p>For Authentication templates, Meta controls the preset text and OTP button structure. Prefer creating it from Meta's Authentication Template library rather than submitting an ordinary custom text template. Saflivo sends the code as the body parameter and the OTP button parameter required by Meta's authentication-template send endpoint.</p>
        <p>OTP is an additional delivery to the existing email OTP. It is attempted only when the login request arrives in an organization context, WhatsApp is permitted for that organization, an international-format phone number is available and the recipient has opted in to WhatsApp messages, and an approved Authentication template is configured for the resolved channel. If WhatsApp delivery is unavailable, the email OTP remains the primary path.</p>
        <p><strong>Official reference:</strong> <a href="https://developers.facebook.com/docs/whatsapp/business-management-api/authentication-templates" target="_blank" rel="noopener">Meta Authentication Templates</a> and <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates/auth-otp-template-messages" target="_blank" rel="noopener">Sending Authentication Templates</a>.</p>
    </section>

    <section class="card">
        <h2>Service permissions and organization charging</h2>
        <ul>
            <li><strong>OTP / Authentication:</strong> included by default when the System Admin allows WhatsApp for the organization.</li>
            <li><strong>Utility:</strong> requires a separate System Admin approval and the matching approved Utility template.</li>
            <li><strong>Marketing / Promotional:</strong> requires a separate System Admin approval, the matching approved Marketing template, and appropriate recipient opt-in.</li>
        </ul>
        <p>Configure Saflivo's system credentials at <code>/system/whatsapp-settings</code>. The same page controls WhatsApp feature access, default-channel permission, and Utility/Marketing approvals for each organization. System Admin credentials are encrypted and the saved access token is not shown again.</p>
        <p>An Organization Super Administrator can configure organization-owned credentials at <code>/organization/notifications</code> after the System Admin enables the WhatsApp feature. An organization's own channel takes precedence when enabled and complete. Saflivo's default channel is used only if the System Admin has allowed both the WhatsApp feature and the default channel for that organization.</p>
        <p>Usage is recorded at <code>/system/whatsapp-usage</code> and separated into OTP, Utility and Marketing. These are Saflivo-attributed message counts. Meta does not provide a separate invoice per Saflivo tenant when tenants share a WABA/phone number; actual account costs must be reconciled against Meta's account-level billing and status/analytics data, then allocated using Saflivo's usage ledger and approved rates.</p>
    </section>

    <section class="card">
        <h2>Operational limitations</h2>
        <ul>
            <li>A successful API response means Meta accepted the send request; it does not by itself prove the message was delivered or read.</li>
            <li>Delivery/read receipts require a configured Meta webhook and corresponding Saflivo handling.</li>
            <li>Recipients must have provided the appropriate opt-in. Follow Meta's messaging policies and applicable privacy requirements.</li>
            <li>Do not include passwords, OTPs, identity-document numbers, or other sensitive personal data in general notification templates.</li>
            <li>Template approval is controlled by Meta; Saflivo cannot guarantee approval.</li>
        </ul>
    </section>
        </div>
    </div>
</div>
