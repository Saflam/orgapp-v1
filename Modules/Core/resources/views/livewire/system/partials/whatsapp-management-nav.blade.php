<nav class="long-page-nav" aria-label="WhatsApp management navigation">
    <span class="long-page-nav-title">WhatsApp menu</span>
    <a href="/system/whatsapp-settings" @if (($active ?? '') === 'settings') aria-current="page" @endif>Default channel settings</a>
    <a href="/system/whatsapp-templates" @if (($active ?? '') === 'templates') aria-current="page" @endif>Template guide</a>
    <a href="/system/whatsapp-usage" @if (($active ?? '') === 'usage') aria-current="page" @endif>Usage &amp; limits</a>
    <a href="/system/whatsapp-usage-records" @if (($active ?? '') === 'records') aria-current="page" @endif>Usage records</a>
    <a href="/system/whatsapp-cost-reconciliation" @if (($active ?? '') === 'costs') aria-current="page" @endif>Cost reporting &amp; reconciliation</a>
    <a href="/system/whatsapp-message-history" @if (($active ?? '') === 'history') aria-current="page" @endif>Message history</a>
    <a href="/system/whatsapp-failed-deliveries" @if (($active ?? '') === 'failed') aria-current="page" @endif>Failed deliveries</a>
</nav>