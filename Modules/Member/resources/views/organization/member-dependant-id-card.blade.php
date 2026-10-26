@extends('layouts.app')
@push('head')
    @vite('resources/css/member-dependant-id-card.css')
@endpush
@section('title', 'Dependant ID Card — '.$organization->name)
@section('content')
<div class="min-h-screen bg-slate-100 px-3 py-5 sm:px-4 sm:py-8" style="--org-primary: {{ $primaryColor }}">
    <main class="mx-auto w-full max-w-sm">
        @unless(request()->boolean('modal'))
            <a href="{{ route('organization.member.profile') }}#family" class="mb-3 inline-flex text-sm font-semibold text-slate-600 print:hidden">← Back to family</a>
        @endunless
        <article id="dependant-id-card" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl print:rounded-none print:border-0 print:shadow-none">
            <header class="px-4 py-4 text-center text-white" style="background:var(--org-primary)">
                @if(data_get($organization->settings, 'branding.logo_path'))
                    <img src="{{ data_get($organization->settings, 'branding.logo_path') }}" alt="Organization logo" class="mx-auto mb-2 h-10 max-w-28 object-contain">
                @endif
                <p class="text-[11px] font-semibold uppercase tracking-widest">{{ $organization->name }}</p>
                <h1 class="mt-1 text-lg font-extrabold">DEPENDANT ID CARD</h1>
            </header>
            <div class="p-4">
                <div class="flex flex-col items-center text-center">
                    <div class="h-28 w-24 overflow-hidden rounded-lg border-2 bg-slate-100" style="border-color:var(--org-primary)">
                        @if($dependant->getFirstMedia('profile-photo'))
                            <img src="{{ route('organization.member.dependants.photo', $dependant->id) }}" alt="Dependant photo" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-3xl font-bold" style="color:var(--org-primary)">{{ mb_substr($dependant->first_name,0,1) }}</div>
                        @endif
                    </div>
                    <h2 data-card-first-name="{{ $dependant->first_name }}" class="mt-3 break-words text-xl font-extrabold text-slate-900">{{ trim($dependant->first_name.' '.$dependant->middle_name.' '.$dependant->last_name) }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Dependant ID: <strong data-card-membership-number="DEP-{{ str_pad((string)$dependant->id, 6, '0', STR_PAD_LEFT) }}">DEP-{{ str_pad((string)$dependant->id, 6, '0', STR_PAD_LEFT) }}</strong></p>
                </div>
                <div class="mt-4 space-y-2 rounded-lg border border-slate-200 p-3 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Relationship</span><strong class="text-right text-slate-900">{{ ucfirst(str_replace('_', ' ', $dependant->pivot->relationship_type ?? 'dependant')) }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Gender</span><strong class="text-right text-slate-900">{{ ucfirst(str_replace('_', ' ', $dependant->gender)) }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Date of Birth</span><strong class="text-right text-slate-900">{{ $dependant->date_of_birth?->format('d/m/Y') ?: '—' }}</strong></div>
                </div>
                <section class="mt-4 rounded-lg border border-slate-200 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide" style="color:var(--org-primary)">Parent / Primary Member</p>
                    <p class="mt-1 text-sm font-extrabold text-slate-900">{{ $member->user?->name }}</p>
                    <p class="mt-1 text-sm text-slate-600">Membership No: {{ $member->membership_number ?: 'Pending' }}</p>
                </section>
                <div class="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                    <img src="{{ $qrCodeDataUri }}" alt="Secure dependant verification QR code" class="h-24 w-24 shrink-0">
                    <div class="min-w-0"><p class="text-sm font-bold" style="color:var(--org-primary)">{{ $organization->name }}</p><p class="mt-1 text-xs text-slate-600">Scan to verify this dependant record. Sign-in and member-view permission are required.</p></div>
                </div>
            </div>
            <footer class="border-t border-slate-200 px-4 py-2 text-center text-xs text-slate-600">If found, please return this card to the organization.</footer>
        </article>
        <div class="mt-3 flex flex-wrap justify-center gap-2 print:hidden">
            <button type="button" onclick="downloadIdCardJpeg('dependant-id-card', 'dependant-id-card.jpg', this)" class="rounded-xl px-4 py-3 text-sm font-bold text-white" style="background:var(--org-primary)">Download JPEG</button>
            <button type="button" onclick="window.print()" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700">Print / Save PDF</button>
            @unless(request()->boolean('modal'))
                <a href="{{ route('organization.member.profile') }}#family" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700">Close</a>
            @endunless
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
async function downloadIdCardJpeg(elementId, filename, button) {
    const original = document.getElementById(elementId);
    const safeFilePart = value => String(value || '')
        .normalize('NFKD')
        .replace(/[^a-zA-Z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
    const firstName = safeFilePart(original?.querySelector('[data-card-first-name]')?.textContent);
    const membershipNumber = safeFilePart(original?.querySelector('[data-card-membership-number]')?.textContent);
    if (firstName || membershipNumber) {
        filename = `${firstName || 'member'}-${membershipNumber || 'ID'}.jpg`;
    }
    if (!original) return;
    const originalText = button ? button.textContent : '';
    if (button) { button.disabled = true; button.textContent = 'Preparing JPEG…'; }

    // html2canvas can fail while parsing the application's Tailwind stylesheet,
    // before it reaches the card. Build a self-contained clone with computed styles
    // inline, then remove stylesheets from the cloned document before rendering.
    const unsafeColor = value => typeof value === 'string' && value.includes('oklch');
    const safeColor = (value, fallback) => unsafeColor(value) ? fallback : value;
    const colorFallbacks = {
        color: 'rgb(15, 23, 42)',
        backgroundColor: 'rgba(0, 0, 0, 0)',
        borderTopColor: 'rgb(203, 213, 225)',
        borderRightColor: 'rgb(203, 213, 225)',
        borderBottomColor: 'rgb(203, 213, 225)',
        borderLeftColor: 'rgb(203, 213, 225)',
        outlineColor: 'rgb(203, 213, 225)',
        textDecorationColor: 'rgb(15, 23, 42)'
    };

    try {
        if (typeof window.html2canvas !== 'function') {
            throw new Error('JPEG export library could not be loaded. Check your internet connection and try again.');
        }

        const clone = original.cloneNode(true);
        const sourceElements = [original, ...original.querySelectorAll('*')];
        const cloneElements = [clone, ...clone.querySelectorAll('*')];

        sourceElements.forEach((source, index) => {
            const target = cloneElements[index];
            if (!target) return;
            const computed = getComputedStyle(source);

            // Copy the computed declarations, not the original Tailwind classes.
            // The browser resolves modern color functions to serializable RGB values.
            for (let i = 0; i < computed.length; i++) {
                const property = computed[i];
                let value = computed.getPropertyValue(property);
                if (!value) continue;
                if (unsafeColor(value)) {
                    const camelProperty = property.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                    value = colorFallbacks[camelProperty] || 'rgb(100, 116, 139)';
                }
                try { target.style.setProperty(property, value, 'important'); } catch (_) {}
            }

            // Avoid stale stylesheet-driven pseudo/UI effects and modern color functions.
            target.style.setProperty('box-shadow', 'none', 'important');
            target.style.setProperty('text-shadow', 'none', 'important');
        });

        // Apply export layout AFTER copying computed styles; the previous ordering
        // allowed computed styles to overwrite the attempted overlap fix.
        clone.querySelectorAll('h2').forEach(el => {
            const nameLength = (el.textContent || '').trim().length;
            const fontSize = nameLength > 32 ? 18 : nameLength > 24 ? 20 : 24;
            el.style.setProperty('display', 'block', 'important');
            el.style.setProperty('position', 'static', 'important');
            el.style.setProperty('box-sizing', 'border-box', 'important');
            el.style.setProperty('width', '100%', 'important');
            el.style.setProperty('max-width', '100%', 'important');
            el.style.setProperty('height', 'auto', 'important');
            el.style.setProperty('min-height', '0', 'important');
            el.style.setProperty('margin', '12px 0 8px', 'important');
            el.style.setProperty('padding', '0 4px', 'important');
            el.style.setProperty('line-height', '1.25', 'important');
            el.style.setProperty('font-size', `${fontSize}px`, 'important');
            el.style.setProperty('text-align', 'center', 'important');
            el.style.setProperty('white-space', 'normal', 'important');
            el.style.setProperty('overflow-wrap', 'anywhere', 'important');
            el.style.setProperty('word-break', 'normal', 'important');
        });
        clone.querySelectorAll('p').forEach(el => {
            el.style.setProperty('position', 'static', 'important');
            el.style.setProperty('height', 'auto', 'important');
            el.style.setProperty('min-height', '0', 'important');
            el.style.setProperty('line-height', '1.45', 'important');
        });
        clone.querySelectorAll('strong').forEach(el => {
            el.style.setProperty('position', 'static', 'important');
            el.style.setProperty('line-height', '1.35', 'important');
        });
        clone.querySelectorAll('img').forEach(el => {
            el.style.setProperty('flex-shrink', '0', 'important');
        });

        if (elementId === 'member-id-card') {
            const membershipRow = Array.from(clone.querySelectorAll('p')).find(el =>
                el.textContent.includes('Membership No.')
            );
            if (membershipRow) {
                membershipRow.style.setProperty('display', 'flex', 'important');
                membershipRow.style.setProperty('flex-direction', 'row', 'important');
                membershipRow.style.setProperty('flex-wrap', 'nowrap', 'important');
                membershipRow.style.setProperty('align-items', 'baseline', 'important');
                membershipRow.style.setProperty('justify-content', 'center', 'important');
                membershipRow.style.setProperty('gap', '8px', 'important');
                membershipRow.style.setProperty('white-space', 'nowrap', 'important');
                membershipRow.style.setProperty('margin', '4px 0 0', 'important');
                membershipRow.style.setProperty('font-size', '16px', 'important');
            }
        }

        clone.style.setProperty('position', 'fixed', 'important');
        clone.style.setProperty('left', '-10000px', 'important');
        clone.style.setProperty('top', '0', 'important');
        clone.style.setProperty('width', `${original.getBoundingClientRect().width}px`, 'important');
        clone.style.setProperty('max-width', 'none', 'important');
        clone.style.setProperty('border', '1px solid rgb(229, 231, 235)', 'important');
        clone.style.setProperty('background-color', 'rgb(255, 255, 255)', 'important');
        document.body.appendChild(clone);

        try {
            const canvas = await window.html2canvas(clone, {
                scale: Math.max(2, window.devicePixelRatio || 1),
                backgroundColor: '#ffffff',
                useCORS: true,
                allowTaint: false,
                logging: false,
                onclone: clonedDocument => {
                    // Crucial: remove the CSS sources containing oklch() before
                    // html2canvas parses stylesheets in its cloned document.
                    clonedDocument.querySelectorAll('style, link[rel="stylesheet"]').forEach(node => node.remove());
                    clonedDocument.querySelectorAll('*').forEach(element => {
                        ['color', 'background-color', 'border-top-color', 'border-right-color',
                         'border-bottom-color', 'border-left-color', 'outline-color',
                         'text-decoration-color'].forEach(property => {
                            const value = element.style.getPropertyValue(property);
                            if (unsafeColor(value)) {
                                element.style.setProperty(property, 'rgb(100, 116, 139)', 'important');
                            }
                        });
                    });
                }
            });

            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/jpeg', 0.96);
            document.body.appendChild(link);
            link.click();
            link.remove();
        } finally {
            clone.remove();
        }
    } catch (error) {
        alert(error && error.message ? error.message : 'Unable to create the JPEG. Please try again.');
    } finally {
        if (button) { button.disabled = false; button.textContent = originalText; }
    }
}
</script>
@endsection
