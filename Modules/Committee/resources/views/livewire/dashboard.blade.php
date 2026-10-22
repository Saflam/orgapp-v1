<div class="committee-page committee-dashboard">
    <header class="committee-page-header">
        <div>
            <p class="committee-eyebrow">Organization workspace</p>
            <h1>Committee management</h1>
            <p class="committee-page-description">Manage committees, designations, and committee membership assignments for this organization.</p>
        </div>
    </header>

    <section class="committee-stat-grid" aria-label="Committee summary">
        <a href="{{ route('committee.committees') }}" class="committee-stat-card">
            <span class="committee-stat-label">Committees</span>
            <strong class="committee-stat-number">{{ number_format($committeeCount) }}</strong>
            <span class="committee-stat-action">View committees <span aria-hidden="true">→</span></span>
        </a>
        <a href="{{ route('committee.designations') }}" class="committee-stat-card">
            <span class="committee-stat-label">Designations</span>
            <strong class="committee-stat-number">{{ number_format($designationCount) }}</strong>
            <span class="committee-stat-action">View designations <span aria-hidden="true">→</span></span>
        </a>
        <div class="committee-stat-card">
            <span class="committee-stat-label">Membership assignments</span>
            <strong class="committee-stat-number">{{ number_format($membershipCount) }}</strong>
            <span class="committee-stat-footnote">Across this organization</span>
        </div>
    </section>

    <aside class="committee-notice" role="note">
        <strong>Initial release</strong>
        <p>This is the initial Committee UI shell. Creating and editing committees, designations, designation permissions, and member assignments will be added in the next implementation steps.</p>
    </aside>
</div>
