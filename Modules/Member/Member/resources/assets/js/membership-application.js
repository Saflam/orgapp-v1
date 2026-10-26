(() => {
    const root = document.querySelector('[data-membership-application]');
    if (!root) return;

    const loading = document.getElementById('membership-loading');
    const loadError = document.getElementById('membership-load-error');
    const form = document.getElementById('membership-application-form');
    const panels = document.getElementById('membership-panels');
    const stepper = document.getElementById('membership-stepper');
    const previousButton = document.getElementById('membership-previous');
    const nextButton = document.getElementById('membership-next');
    const errorBox = document.getElementById('membership-form-error');
    const errorList = errorBox.querySelector('[data-error-list]');
    const success = document.getElementById('membership-success');
    const progress = document.getElementById('membership-progress');

    let config = null;
    let steps = [];
    let currentStep = 0;
    let submitting = false;
    let emergencyRowSequence = 0;
    let draft = null;
    let cropModal = null;
    let activeCrop = null;
    let draftSaveTimer = null;

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const ensureCropModal = () => {
        if (cropModal) return cropModal;
        cropModal = document.createElement('div');
        cropModal.className = 'fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4';
        cropModal.innerHTML = `
            <div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-2xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="crop-dialog-title">
                <div class="flex items-start justify-between gap-3"><div><h2 id="crop-dialog-title" class="text-lg font-bold text-slate-950">Crop image</h2><p class="mt-1 text-sm text-slate-600">Drag the image to adjust the crop, then use zoom if needed.</p></div><button type="button" data-crop-cancel aria-label="Cancel crop" class="rounded-full px-3 py-2 text-lg">×</button></div>
                <div class="mx-auto mt-5 overflow-hidden rounded-xl bg-slate-900 touch-none" data-crop-viewport style="position:relative;width:min(82vw,320px);height:320px"><img alt="Image to crop" draggable="false" data-crop-image style="position:absolute;max-width:none;user-select:none;touch-action:none"></div>
                <label class="mt-5 block text-sm font-semibold text-slate-700" for="crop-zoom">Zoom</label><input id="crop-zoom" class="mt-2 w-full" type="range" min="1" max="3" step="0.05" value="1" data-crop-zoom>
                <div class="mt-5 flex justify-end gap-3"><button type="button" data-crop-cancel class="membership-secondary min-h-11 rounded-full px-4 text-sm font-semibold">Cancel</button><button type="button" data-crop-apply class="membership-primary min-h-11 rounded-full px-5 text-sm font-semibold">Use cropped image</button></div>
            </div>`;
        document.body.appendChild(cropModal);
        const viewport = cropModal.querySelector('[data-crop-viewport]');
        const image = cropModal.querySelector('[data-crop-image]');
        const zoom = cropModal.querySelector('[data-crop-zoom]');
        const close = () => {
            cropModal.classList.add('hidden'); cropModal.classList.remove('flex');
            if (activeCrop?.objectUrl) URL.revokeObjectURL(activeCrop.objectUrl);
            activeCrop = null;
        };
        const layoutImage = () => {
            if (!activeCrop || !image.naturalWidth) return;
            const scale = activeCrop.baseScale * Number(zoom.value);
            activeCrop.scale = scale;
            image.style.width = `${image.naturalWidth * scale}px`;
            image.style.height = `${image.naturalHeight * scale}px`;
            const minLeft = viewport.clientWidth - image.naturalWidth * scale;
            const minTop = viewport.clientHeight - image.naturalHeight * scale;
            activeCrop.left = Math.min(0, Math.max(minLeft, activeCrop.left));
            activeCrop.top = Math.min(0, Math.max(minTop, activeCrop.top));
            image.style.left = `${activeCrop.left}px`;
            image.style.top = `${activeCrop.top}px`;
        };
        zoom.addEventListener('input', () => {
            if (!activeCrop) return;
            const oldScale = activeCrop.scale || activeCrop.baseScale;
            const nextScale = activeCrop.baseScale * Number(zoom.value);
            const centerX = viewport.clientWidth / 2;
            const centerY = viewport.clientHeight / 2;
            const sourceCenterX = (centerX - activeCrop.left) / oldScale;
            const sourceCenterY = (centerY - activeCrop.top) / oldScale;
            activeCrop.left = centerX - sourceCenterX * nextScale;
            activeCrop.top = centerY - sourceCenterY * nextScale;
            layoutImage();
        });
        let drag = null;
        const startDrag = (event) => {
            if (!activeCrop) return;
            drag = { x: event.clientX, y: event.clientY, left: activeCrop.left, top: activeCrop.top };
            image.setPointerCapture?.(event.pointerId);
        };
        image.addEventListener('pointerdown', startDrag);
        image.addEventListener('pointermove', (event) => {
            if (!drag || !activeCrop) return;
            activeCrop.left = drag.left + event.clientX - drag.x;
            activeCrop.top = drag.top + event.clientY - drag.y;
            layoutImage();
        });
        image.addEventListener('pointerup', () => { drag = null; });
        image.addEventListener('pointercancel', () => { drag = null; });
        cropModal.querySelectorAll('[data-crop-cancel]').forEach((button) => button.addEventListener('click', close));
        cropModal.querySelector('[data-crop-apply]').addEventListener('click', () => {
            if (!activeCrop) return;
            const { input, file, image: sourceImage, scale, left, top, aspect } = activeCrop;
            const viewWidth = viewport.clientWidth;
            const viewHeight = viewport.clientHeight;
            const sx = Math.max(0, -left / scale);
            const sy = Math.max(0, -top / scale);
            const sw = Math.min(sourceImage.naturalWidth - sx, viewWidth / scale);
            const sh = Math.min(sourceImage.naturalHeight - sy, viewHeight / scale);
            const canvas = document.createElement('canvas');
            canvas.width = aspect === 'profile' ? 600 : 1000;
            canvas.height = aspect === 'profile' ? 600 : 630;
            const context = canvas.getContext('2d');
            context.drawImage(sourceImage, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                if (!blob) return;
                const cropped = new File([blob], file.name.replace(/\.[^.]+$/, '') + '-cropped.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                const transfer = new DataTransfer(); transfer.items.add(cropped); input.files = transfer.files;
                renderFilePreview(input, cropped);
                close();
            }, 'image/jpeg', 0.92);
        });
        return cropModal;
    };

    const renderFilePreview = (input, file = input.files?.[0]) => {
        const wrapper = input.closest('.membership-field')?.querySelector('[data-file-preview]');
        if (!wrapper) return;
        const image = wrapper.querySelector('[data-file-preview-image]');
        const pdf = wrapper.querySelector('[data-file-preview-pdf]');
        const name = wrapper.querySelector('[data-file-preview-name]');
        if (!file) { wrapper.classList.add('hidden'); return; }
        wrapper.classList.remove('hidden');
        if (name) name.textContent = file.name;
        if (file.type.startsWith('image/')) {
            if (pdf) pdf.classList.add('hidden');
            if (image) {
                image.classList.remove('hidden');
                if (image.dataset.objectUrl) URL.revokeObjectURL(image.dataset.objectUrl);
                image.dataset.objectUrl = URL.createObjectURL(file);
                image.src = image.dataset.objectUrl;
            }
        } else {
            image?.classList.add('hidden');
            pdf?.classList.remove('hidden');
        }
    };

    const openCrop = (input, file) => {
        const modal = ensureCropModal();
        const viewport = modal.querySelector('[data-crop-viewport]');
        const image = modal.querySelector('[data-crop-image]');
        const zoom = modal.querySelector('[data-crop-zoom]');
        const objectUrl = URL.createObjectURL(file);
        const sourceImage = new Image();
        sourceImage.onload = () => {
            const isProfile = input.hasAttribute('data-profile-photo');
            const aspect = isProfile ? 'profile' : 'document';
            viewport.style.height = isProfile ? '320px' : '202px';
            modal.classList.remove('hidden'); modal.classList.add('flex');
            requestAnimationFrame(() => {
                const viewWidth = viewport.clientWidth;
                const viewHeight = isProfile ? 320 : 202;
                const baseScale = Math.max(viewWidth / sourceImage.naturalWidth, viewHeight / sourceImage.naturalHeight);
                activeCrop = { input, file, image: sourceImage, objectUrl, aspect, baseScale, scale: baseScale, left: (viewWidth - sourceImage.naturalWidth * baseScale) / 2, top: (viewHeight - sourceImage.naturalHeight * baseScale) / 2 };
                image.src = objectUrl;
                image.style.width = `${sourceImage.naturalWidth * baseScale}px`;
                image.style.height = `${sourceImage.naturalHeight * baseScale}px`;
                image.style.left = `${activeCrop.left}px`;
                image.style.top = `${activeCrop.top}px`;
                zoom.value = '1';
            });
        };
        sourceImage.onerror = () => { URL.revokeObjectURL(objectUrl); showErrors(['This image could not be opened. Please choose another image.']); };
        sourceImage.src = objectUrl;
    };

    const bindFileInputs = () => {
        form.querySelectorAll('input[type="file"][data-crop-upload]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;
                if (file.type.startsWith('image/')) { renderFilePreview(input, file); openCrop(input, file); }
                else renderFilePreview(input, file);
            });
        });
    };

    const countryNames = (() => {
        try { return new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }); }
        catch (_) { return { of: (code) => code }; }
    })();

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[character]);

    const humanize = (value) => String(value ?? '')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

    const countryLabel = (country) => {
        let name = country.iso2;
        try { name = countryNames.of(country.iso2) || country.iso2; } catch (_) { /* Use ISO code. */ }
        return `${name} (+${country.calling_code})`;
    };

    const countryOptions = (selected = 'IN', includeCallingCode = true) => {
        const countries = [...(config?.countries ?? [])].sort((a, b) => {
            let aName = a.iso2;
            let bName = b.iso2;
            try { aName = countryNames.of(a.iso2) || a.iso2; bName = countryNames.of(b.iso2) || b.iso2; } catch (_) { /* Keep ISO code. */ }
            return aName.localeCompare(bName);
        });
        return countries.map((country) => {
            let label = countryLabel(country);
            if (!includeCallingCode) {
                try { label = countryNames.of(country.iso2) || country.iso2; } catch (_) { label = country.iso2; }
            }
            return `<option value="${escapeHtml(country.iso2)}" ${country.iso2 === selected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    };

    const defaultCountry = () => {
        const candidates = ['IN', 'AE', 'GB', 'US'];
        return candidates.find((iso) => config?.countries?.some((country) => country.iso2 === iso)) || config?.countries?.[0]?.iso2 || 'IN';
    };

    const countryByIso = (iso) => config?.countries?.find((country) => country.iso2 === iso) || null;

    const countrySelectorForPhoneInput = (input) => {
        if (input.name === 'primary_phone') return form.querySelector('[name="primary_phone_country_iso2"]');

        if (input.hasAttribute('data-extra-phone-number')) {
            return input.closest('[data-phone-list] > div')?.querySelector('[data-extra-phone-country]') || null;
        }

        const emergencyMatch = input.name.match(/^emergency_contacts\[(\d+)\]\[phone\]$/);
        if (emergencyMatch) {
            return form.querySelector(`[name="emergency_contacts[${emergencyMatch[1]}][country_iso2]"]`);
        }

        const field = input.closest('.membership-field');
        const directSelector = field?.querySelector('select[data-phone-country]');
        if (directSelector) return directSelector;

        if (input.name === 'home_contact') {
            return input.closest('[data-address-card]')?.querySelector('select[data-phone-country="home_contact"]') || null;
        }

        return null;
    };

    const internationalCountryForPhone = (value, selectedIso) => {
        let raw = String(value || '').trim();
        if (raw.startsWith('00')) raw = raw.slice(2);
        else if (raw.startsWith('+')) raw = raw.slice(1);
        else return null;

        const digits = raw.replace(/\D/g, '');
        if (!digits) return null;
        const matches = (config?.countries || [])
            .filter((country) => digits.startsWith(String(country.calling_code)))
            .sort((a, b) => String(b.calling_code).length - String(a.calling_code).length);
        if (!matches.length) return null;

        const selectedMatch = matches.find((country) => country.iso2 === selectedIso);
        if (selectedMatch) return selectedMatch;
        return matches.find((country) => country.iso2 === 'US') || matches[0];
    };

    const syncPhoneCountryFromInput = (input) => {
        const selector = countrySelectorForPhoneInput(input);
        if (!selector) return;
        const match = internationalCountryForPhone(input.value, selector.value);
        if (!match || selector.value === match.iso2) return;
        selector.value = match.iso2;
        selector.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const fieldMarkup = ({ name, label, type = 'text', required = false, placeholder = '', hint = '', value = '', options = null, rows = 3, full = false, min = null, max = null }) => {
        const id = `field-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
        const requiredText = required ? '<span class="ml-1 text-rose-600" aria-hidden="true">*</span>' : '';
        const requiredAttribute = required ? 'required' : '';
        let control;
        if (options) {
            control = `<select id="${escapeHtml(id)}" name="${escapeHtml(name)}" class="membership-control" ${requiredAttribute}><option value="">Choose ${escapeHtml(label.toLowerCase())}</option>${options.map((option) => `<option value="${escapeHtml(option.value)}" ${String(option.value) === String(value) ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}</select>`;
        } else if (type === 'textarea') {
            control = `<textarea id="${escapeHtml(id)}" name="${escapeHtml(name)}" rows="${rows}" class="membership-control" placeholder="${escapeHtml(placeholder)}" ${requiredAttribute}>${escapeHtml(value)}</textarea>`;
        } else {
            const fileAttributes = type === 'file' ? 'accept="image/jpeg,image/png,application/pdf" data-crop-upload' : '';
            control = `<input id="${escapeHtml(id)}" name="${escapeHtml(name)}" type="${escapeHtml(type)}" class="membership-control" value="${escapeHtml(value)}" placeholder="${escapeHtml(placeholder)}" ${requiredAttribute} ${min !== null ? `min="${escapeHtml(min)}"` : ''} ${max !== null ? `max="${escapeHtml(max)}"` : ''} ${fileAttributes}>`;
        }
        const preview = type === 'file' ? `<div class="mt-3 hidden" data-file-preview><img class="hidden h-28 max-w-full rounded-xl border border-stone-200 object-contain" alt="Selected document preview" data-file-preview-image><div class="hidden rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-semibold text-slate-700" data-file-preview-pdf>PDF selected</div><p class="mt-1 text-xs text-slate-500" data-file-preview-name></p></div>` : '';
        return `<div class="membership-field ${full ? 'sm:col-span-2' : ''}"><label class="membership-label" for="${escapeHtml(id)}">${escapeHtml(label)}${requiredText}</label>${control}${preview}${hint ? `<p class="mt-1.5 text-xs leading-5 text-slate-500">${escapeHtml(hint)}</p>` : ''}<p class="membership-error mt-1 hidden" data-inline-error="${escapeHtml(name)}"></p></div>`;
    };

    const panelShell = (step, index, content) => `
        <section class="membership-panel membership-card p-4 sm:p-7" data-step-panel="${escapeHtml(step.key)}" ${index === 0 ? '' : 'hidden'} aria-labelledby="step-heading-${index}">
            <div class="mb-6 border-b border-stone-100 pb-5">
                <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--membership-primary)">Step ${index + 1} of ${steps.length}</p>
                <h2 id="step-heading-${index}" class="mt-2 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">${escapeHtml(step.label)}</h2>
                <p class="mt-1.5 text-sm leading-6 text-slate-600">${escapeHtml(stepDescription(step))}</p>
            </div>
            ${content}
        </section>`;

    const stepDescription = (step) => {
        if (step.key === 'personal') return root.dataset.spouseMode === 'true'
            ? 'Tell us about your spouse and how we can reach them.'
            : 'Tell us about yourself and how we can reach you.';
        if (step.key === 'address') return 'Add the address you would like the organization to keep on record.';
        if (step.type === 'extension') return `Complete the additional details requested by the ${step.module} module.`;
        if (step.key === 'documents') return 'Enter the identification details requested by this organization and attach required proof documents.';
        if (step.key === 'summary') return 'Review your details, choose whether to add your spouse, and confirm submission.';
        return 'Complete the details requested by this organization.';
    };

    const fieldFromMetadata = (key, meta, prefix = '') => {
        const name = prefix.endsWith('[') ? `${prefix}${key}]` : `${prefix}${key}`;
        const baseLabel = meta.label || humanize(key);
        const label = root.dataset.spouseMode === 'true'
            ? (baseLabel.toLowerCase().startsWith('spouse ') ? baseLabel : `Spouse ${baseLabel.toLowerCase()}`)
            : baseLabel;
        const required = Boolean(meta.required);
        const type = meta.type || 'string';

        if (key === 'gender') {
            return fieldMarkup({ name, label, required, options: [
                { value: 'female', label: 'Female' }, { value: 'male', label: 'Male' },
                { value: 'non_binary', label: 'Non-binary' }, { value: 'prefer_not_to_say', label: 'Prefer not to say' },
                { value: 'other', label: 'Other' },
            ] });
        }
        if (key === 'blood_group') {
            return fieldMarkup({ name, label, required, options: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'].map((item) => ({ value: item, label: item })) });
        }
        if (key === 'unit_id' || key === 'introducer_unit_id') {
            return fieldMarkup({ name, label, required, options: (config.units || []).map((unit) => ({ value: unit.id, label: unit.name })) });
        }
        if (key.endsWith('_phone') || key.includes('phone')) {
            return fieldMarkup({ name, label, required, type: 'tel', placeholder: 'Enter phone number', hint: 'Include the country calling code if you enter an international number.' });
        }
        if (key.includes('calling_code')) {
            return fieldMarkup({ name, label, required, type: 'text', placeholder: '+971' });
        }
        if (type === 'integer') return fieldMarkup({ name, label, required, type: 'number', min: 0 });
        if (type === 'date') return fieldMarkup({ name, label, required, type: 'date', max: key === 'date_of_birth' ? todayIso() : null });
        if (type === 'email') return fieldMarkup({ name, label, required, type: 'email' });
        if (type === 'tel') return fieldMarkup({ name, label, required, type: 'tel' });
        if (type === 'boolean') return fieldMarkup({ name, label, required, options: [{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }] });
        if (type === 'textarea' || type === 'long_text') return fieldMarkup({ name, label, required, type: 'textarea', full: true });
        return fieldMarkup({ name, label, required, type: 'text' });
    };

    const phoneWidget = ({ key, label, hint = '', required = false }) => `
        <div class="membership-field sm:col-span-2">
            <label class="membership-label" for="${key}-phone">${escapeHtml(label)}${required ? '<span class="ml-1 text-rose-600" aria-hidden="true">*</span>' : ''}</label>
            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] gap-2 sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                <select class="membership-control" aria-label="${escapeHtml(label)} country" data-phone-country="${key}">${countryOptions(defaultCountry())}</select>
                <input id="${key}-phone" name="${key}" type="tel" inputmode="tel" autocomplete="tel-national" class="membership-control" placeholder="Phone number" ${required ? 'required' : ''}>
            </div>
            ${key === 'whatsapp' ? `<input type="hidden" name="whatsapp_calling_code" data-calling-code="whatsapp">` : ''}
            ${hint ? `<p class="mt-1.5 text-xs leading-5 text-slate-500">${escapeHtml(hint)}</p>` : ''}
        </div>`;

    const todayIso = () => {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    };

    const personalPanel = () => {
        const personal = config.personal?.fields || {};
        const mainKeys = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'blood_group', 'profession', 'company'];
        let fields = '';
        mainKeys.forEach((key) => {
            if (!personal[key]) return;
            if (key === 'date_of_birth') {
                fields += fieldMarkup({ name: key, label: personal[key].label || 'Date of birth', type: 'date', required: Boolean(personal[key].required), max: todayIso(), hint: 'Your date of birth cannot be in the future.' });
            } else if (key === 'company') {
                fields += fieldMarkup({ name: key, label: personal[key].label || 'Company / employer', required: Boolean(personal[key].required), placeholder: 'Company or employer name' });
            } else {
                fields += fieldFromMetadata(key, personal[key]);
            }
        });

        const existingPhone = config.current_user_phone || {};
        const primaryCountry = existingPhone.country_iso2 || defaultCountry();
        const primaryNumber = root.dataset.spouseMode === 'true' ? '' : (existingPhone.national_number || root.dataset.userPhone || '');

        return `
            <section class="mb-7 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 sm:p-5" aria-labelledby="preferred-unit-heading">
                <div class="mb-4">
                    <h3 id="preferred-unit-heading" class="text-base font-bold text-slate-950 sm:text-lg">${root.dataset.spouseMode === 'true' ? "Spouse’s preferred unit" : "Your preferred unit"}</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-600">This application is for joining the unit you select below.</p>
                </div>
                <div class="membership-field">
                    <label class="membership-label" for="preferred-unit-id">${root.dataset.spouseMode === 'true' ? "Spouse’s unit" : "Select a unit"} <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <select id="preferred-unit-id" name="preferred_unit_id" class="membership-control" required><option value="">Choose a unit</option>${(config.units || []).map((unit) => `<option value="${escapeHtml(unit.id)}">${escapeHtml(unit.name)}</option>`).join('')}</select>
                </div>
            </section>
            ${root.dataset.spouseMode === 'true' ? `<div class="mb-6 rounded-2xl bg-emerald-50 p-4 sm:p-5 text-sm leading-6 text-emerald-900">This is a separate application for ${escapeHtml(root.dataset.spouseApplicantName || 'your spouse')}. Details copied from the first application can be reviewed and changed before submission.</div>${fieldMarkup({ name: 'email', label: 'Spouse email address', type: 'email', required: true, placeholder: 'name@example.com', full: true })}` : `<div class="mb-6 rounded-2xl bg-stone-50 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm">◎</span>
                    <div class="min-w-0"><p class="text-sm font-semibold text-slate-900">Your account</p><p class="mt-1 truncate text-sm text-slate-600">${escapeHtml(root.dataset.userName || '')}</p><p class="truncate text-xs text-slate-500">${escapeHtml(root.dataset.userEmail || '')}</p></div>
                </div>
            </div>`}
            ${root.dataset.spouseMode !== 'true' ? `<p class="mb-3 text-sm leading-6 text-slate-600">We’ve prefilled your name from your account where possible. Please check the first, middle and last name fields and correct them if needed.</p>` : ''}
            <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">${fields}</div>
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="membership-field"><label class="membership-label" for="company-address">Company address / work location</label><input id="company-address" name="company_address" class="membership-control" type="text" maxlength="255" placeholder="Enter a location or place" /></div>
            </div>
            <div class="mt-7 border-t border-stone-100 pt-6">
                <h3 class="text-base font-bold text-slate-950">Primary phone number <span class="text-rose-600" aria-hidden="true">*</span></h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">Used as an alternate way to sign in. If you change this number, it will need verification before phone sign-in can use it. Paste an international number beginning with + and the country selector will update automatically.</p>
                <div class="mt-4 grid grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-2">
                    <select class="membership-control" aria-label="Primary phone country" name="primary_phone_country_iso2" required>${countryOptions(primaryCountry)}</select>
                    <input id="primary-phone" name="primary_phone" type="tel" inputmode="tel" autocomplete="tel-national" class="membership-control" required value="${escapeHtml(primaryNumber)}" placeholder="Phone number" />
                </div>
            </div>
            <div class="mt-7 border-t border-stone-100 pt-6">
                <h3 class="text-base font-bold text-slate-950">WhatsApp number</h3><p class="mt-1 text-sm text-slate-600">Optional. Choose the country for a local number, or paste an international number beginning with +.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">${phoneWidget({ key: 'whatsapp', label: 'WhatsApp number' })}</div>
            </div>
            <div class="mt-7 border-t border-stone-100 pt-6">
                <div class="mb-4"><h3 class="text-base font-bold text-slate-950">Profile photo <span class="text-rose-600" aria-hidden="true">*</span></h3><p class="mt-1 text-sm text-slate-600">Upload a recent clear photo of yourself. JPG or PNG, up to 5 MB.</p></div>
                <div class="membership-field"><label class="membership-label" for="profile-photo">Profile photo</label><input id="profile-photo" name="profile_photo" type="file" class="membership-control" accept="image/jpeg,image/png" required data-profile-photo data-crop-upload><div class="mt-3 hidden" data-file-preview><img class="hidden h-28 w-28 rounded-2xl border border-stone-200 object-cover" alt="Profile photo preview" data-file-preview-image><p class="mt-1 text-xs text-slate-500" data-file-preview-name></p></div><p class="membership-error mt-1 hidden" data-inline-error="profile_photo"></p></div>
            </div>
            <div class="mt-7 border-t border-stone-100 pt-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h3 class="text-base font-bold text-slate-950">Additional phone numbers</h3><p class="mt-1 text-sm text-slate-600">Optional work or alternate numbers.</p></div><button type="button" data-add-phone class="membership-secondary inline-flex min-h-11 items-center justify-center rounded-full px-4 text-sm font-semibold">＋ Add number</button></div>
                <div class="mt-4 space-y-3" data-phone-list></div>
            </div>
            <div class="mt-7 border-t border-stone-100 pt-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h3 class="text-base font-bold text-slate-950">Emergency contact <span class="text-rose-600" aria-hidden="true">*</span></h3><p class="mt-1 text-sm text-slate-600">Add at least one person we can contact in an emergency.</p></div><button type="button" data-add-emergency class="membership-secondary inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-full px-4 text-sm font-semibold">＋ Add contact</button></div>
                <div class="mt-4 space-y-3" data-emergency-list></div>
            </div>`;
    };

    const addressFieldsMarkup = (type, label, index, required = true, values = {}) => {
        const name = (field) => `addresses[${index}][${field}]`;
        return `<div class="rounded-2xl border border-stone-200 p-4 sm:p-5" data-address-card data-address-type="${escapeHtml(type)}">
            <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="font-bold text-slate-950">${escapeHtml(label)} ${required ? '<span class="text-rose-600">*</span>' : ''}</h3><p class="mt-1 text-sm text-slate-500">${type === 'residential' ? 'Where you currently live.' : type === 'permanent' ? 'Your permanent or family address.' : 'An additional address for organization records.'}</p></div>${required ? '' : '<button type="button" data-remove-address class="min-h-10 rounded-full px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove</button>'}</div>
            ${required ? `<input type="hidden" name="${name('address_type')}" value="${escapeHtml(type)}">` : fieldMarkup({ name: name('address_type'), label: 'Address type', required: true, options: [{ value: 'mailing', label: 'Mailing' }, { value: 'work', label: 'Work / office' }, { value: 'temporary', label: 'Temporary' }, { value: 'other', label: 'Other' }] })}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                ${fieldMarkup({ name: name('address_line_1'), label: 'Address / location', required: true, placeholder: 'House, street, building or place', value: values.address_line_1 || '', full: true })}
                ${fieldMarkup({ name: name('address_line_2'), label: 'Address line 2', required: false, placeholder: 'Apartment, landmark (optional)', value: values.address_line_2 || '', full: true })}
                ${fieldMarkup({ name: name('locality'), label: 'Locality', required: false, value: values.locality || '' })}
                ${fieldMarkup({ name: name('city'), label: 'City / town', required: false, value: values.city || '' })}
                ${fieldMarkup({ name: name('state'), label: 'State / province', required: false, value: values.state || '' })}
                ${fieldMarkup({ name: name('postal_code'), label: 'Postal code', required: false, value: values.postal_code || '' })}
                ${fieldMarkup({ name: name('country'), label: 'Country', required: true, value: values.country || '' })}
                <input type="hidden" name="${name('is_primary')}" value="${index === 0 ? '1' : '0'}">
            </div>
            ${type === 'permanent' ? `<div class="mt-5 border-t border-stone-100 pt-4"><label class="membership-label" for="home-contact-phone">Home phone (optional)</label><div class="grid grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-2"><select class="membership-control" aria-label="Home phone country" data-phone-country="home_contact">${countryOptions(defaultCountry())}</select><input id="home-contact-phone" name="home_contact" type="tel" inputmode="tel" class="membership-control" placeholder="Home phone number"></div></div>` : ''}
        </div>`;
    };

    const addressPanel = () => `
        <div class="space-y-4" data-address-list>
            ${addressFieldsMarkup('residential', 'Local / residential address', 0, true)}
            ${addressFieldsMarkup('permanent', 'Permanent address', 1, true)}
        </div>
        <button type="button" data-add-address class="membership-secondary mt-4 inline-flex min-h-11 items-center justify-center rounded-full px-4 text-sm font-semibold">＋ Add another address type</button>
        <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-slate-600">Local residential and permanent addresses are required. Additional address types are optional.</p>`;

    const summaryPanel = () => `
        <div class="space-y-6">
            ${root.dataset.spouseMode === 'true' ? '' : `<section class="rounded-2xl border-2 border-emerald-200 bg-emerald-50/40 p-4 sm:p-5" data-spouse-question-section>
                <h3 class="font-bold text-slate-950">Would you like to add your spouse?</h3><p class="mt-1 text-sm leading-6 text-slate-600">Please select one option to continue. Your application and your spouse’s application will be separate.</p>
                <div class="mt-4 flex flex-wrap gap-4"><label class="inline-flex min-h-11 items-center gap-2 text-sm font-medium"><input type="radio" name="spouse[needed]" value="no"> No, not now</label><label class="inline-flex min-h-11 items-center gap-2 text-sm font-medium"><input type="radio" name="spouse[needed]" value="yes"> Yes, add spouse</label></div>
                <p class="mt-2 hidden text-sm font-medium text-rose-700" data-spouse-question-error>Please select whether you want to add your spouse.</p>
                <div class="mt-4 hidden space-y-4" data-spouse-options>
                    <p class="text-sm font-medium text-slate-800">Is your spouse already a member of this organization?</p>
                    <div class="flex flex-wrap gap-4"><label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="spouse[existing]" value="yes"> Yes, find member</label><label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="spouse[existing]" value="no"> No, register spouse</label></div>
                    <div class="hidden" data-spouse-search-wrap><label class="membership-label" for="spouse-search">Search by name, email, phone or membership number</label><input id="spouse-search" type="search" class="membership-control" autocomplete="off" placeholder="Start typing at least 2 characters" data-spouse-search><div class="mt-2 max-h-64 space-y-2 overflow-y-auto" data-spouse-results></div><input type="hidden" name="spouse[member_id]" data-spouse-member-id></div>
                    <p class="hidden rounded-xl bg-amber-50 p-3 text-sm leading-6 text-amber-900" data-spouse-new-note>After you confirm this application, you will be taken to a separate spouse registration form. We will prefill the addresses where possible.</p>
                </div>
            </section>`}
            <section class="rounded-2xl bg-stone-50 p-4 sm:p-5"><h3 class="font-bold text-slate-950">Review your application</h3><p class="mt-1 text-sm text-slate-600">Review the details before confirming submission. You can use Previous to make changes.</p><dl class="mt-4 grid gap-3 sm:grid-cols-2" data-application-summary></dl></section>
            <p class="text-sm leading-6 text-slate-600">By selecting Confirm submission, you confirm that the information provided is accurate to the best of your knowledge.</p>
        </div>`;

    const extensionPanel = (module) => {
        const fields = config.extensions?.[module] || {};
        let content = '';
        Object.entries(fields).forEach(([key, meta]) => { content += fieldFromMetadata(key, meta, `extension_data[${module}][`); });
        return content ? `<div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">${content}</div>` : '<p class="text-sm text-slate-600">No additional information is required for this section.</p>';
    };

    const documentsPanel = () => {
        const identifications = config.identifications || {};
        const documents = config.documents || {};
        let identificationHtml = '';
        Object.entries(identifications).forEach(([code, meta]) => {
            const fields = [];
            fields.push(fieldMarkup({ name: `identifications[${code}]`, label: `${meta.label || humanize(code)} number`, required: Boolean(meta.required), placeholder: `Enter ${String(meta.label || humanize(code)).toLowerCase()} number` }));
            if (meta.has_expiry) fields.push(fieldMarkup({ name: `identification_expiry_dates[${code}]`, label: `${meta.label || humanize(code)} expiry date`, type: 'date', required: false }));
            const documentConfig = documents[code];
            if (documentConfig) {
                (documentConfig.requirements || []).forEach((side) => {
                    fields.push(fieldMarkup({ name: `identification_documents[${code}][${side}]`, label: `${documentConfig.label || meta.label || humanize(code)} — ${humanize(side)}`, type: 'file', required: Boolean(documentConfig.required), hint: 'Accepted formats: JPG, PNG or PDF. Maximum file size: 10 MB.' }));
                });
            }
            identificationHtml += `<div class="rounded-2xl border border-stone-200 p-4 sm:p-5"><h3 class="mb-4 text-sm font-bold text-slate-950">${escapeHtml(meta.label || humanize(code))}</h3><div class="grid grid-cols-1 gap-4 sm:grid-cols-2">${fields.join('')}</div></div>`;
        });
        if (!identificationHtml) identificationHtml = '<div class="rounded-2xl bg-stone-50 p-5 text-sm leading-6 text-slate-600">This organization has not configured any identification details for the application. You can continue.</div>';
        return `<div class="space-y-4">${identificationHtml}<div class="rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Before you submit:</strong> check your details carefully. Required identification fields and documents are configured by the organization.</div></div>`;
    };

    const renderStepper = () => {
        stepper.style.gridTemplateColumns = `repeat(${steps.length}, minmax(0, 1fr))`;
        stepper.innerHTML = steps.map((step, index) => `
            <div class="membership-step min-w-0 text-center" data-step-indicator="${escapeHtml(step.key)}" ${index === currentStep ? 'aria-current="step"' : ''}>
                <div class="membership-step-dot mx-auto flex h-8 w-8 items-center justify-center rounded-full border border-stone-200 bg-white text-xs font-bold text-slate-500 sm:h-9 sm:w-9">${index < currentStep ? '✓' : index + 1}</div>
                <span class="membership-step-label mt-2 truncate text-[11px] font-semibold text-slate-600 sm:text-xs">${escapeHtml(step.label)}</span>
            </div>`).join('');
    };

    const updateNavigation = () => {
        const activeStep = steps[currentStep];
        form.querySelectorAll('[data-step-panel]').forEach((panel) => { panel.hidden = panel.dataset.stepPanel !== activeStep.key; });
        previousButton.disabled = currentStep === 0 || submitting;
        nextButton.disabled = submitting;
        const last = currentStep === steps.length - 1;
        nextButton.innerHTML = last ? (submitting ? 'Submitting…' : 'Confirm submission <span aria-hidden="true">✓</span>') : 'Continue <span aria-hidden="true">→</span>';
        stepper.querySelectorAll('[data-step-indicator]').forEach((indicator, index) => {
            if (index === currentStep) indicator.setAttribute('aria-current', 'step');
            else indicator.removeAttribute('aria-current');
            indicator.dataset.complete = index < currentStep ? 'true' : 'false';
            indicator.querySelector('.membership-step-dot').textContent = index < currentStep ? '✓' : String(index + 1);
        });
        const percent = Math.round(((currentStep + 1) / steps.length) * 100);
        progress.setAttribute('aria-valuenow', String(percent));
        progress.querySelector('[data-progress-fill]').style.width = `${percent}%`;
    };

    const addPhoneRow = () => {
        const list = panels.querySelector('[data-phone-list]');
        if (list.children.length >= 8) {
            showErrors(['You can add up to 8 additional phone numbers. WhatsApp and home phone are counted separately.']);
            return;
        }
        const row = document.createElement('div');
        row.className = 'rounded-2xl border border-stone-200 p-3 sm:p-4';
        row.innerHTML = `
            <div class="mb-3 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-900">Additional number</p><button type="button" data-remove-row class="min-h-10 rounded-full px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove</button></div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                ${fieldMarkup({ name: `extra-phone-type-${Date.now()}`, label: 'Number type', required: true, options: [{ value: 'alternate', label: 'Alternate mobile' }, { value: 'work', label: 'Work' }, { value: 'other', label: 'Other' }] }).replace(/name="[^"]+"/, 'data-extra-phone-type')}
                <div class="membership-field"><label class="membership-label">Country</label><select class="membership-control" data-extra-phone-country>${countryOptions(defaultCountry())}</select></div>
                <div class="membership-field sm:col-span-2"><label class="membership-label">Phone number <span class="text-rose-600" aria-hidden="true">*</span></label><input type="tel" inputmode="tel" class="membership-control" data-extra-phone-number required placeholder="Enter phone number"></div>
            </div>`;
        row.querySelector('[data-remove-row]').addEventListener('click', () => row.remove());
        list.appendChild(row);
    };

    const addEmergencyRow = () => {
        const list = panels.querySelector('[data-emergency-list]');
        if (list.children.length >= 5) {
            showErrors(['You can add up to 5 emergency contacts.']);
            return;
        }
        const index = emergencyRowSequence++;
        const row = document.createElement('div');
        row.className = 'rounded-2xl border border-stone-200 p-3 sm:p-4';
        row.innerHTML = `
            <div class="mb-3 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-900">${root.dataset.spouseMode === 'true' ? 'Spouse emergency contact' : 'Emergency contact'} ${index + 1}</p><button type="button" data-remove-row class="min-h-10 rounded-full px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove</button></div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                ${fieldMarkup({ name: `emergency_contacts[${index}][contact_name]`, label: root.dataset.spouseMode === 'true' ? 'Spouse emergency contact name' : 'Full name', required: true, placeholder: 'Contact person’s name' })}
                ${fieldMarkup({ name: `emergency_contacts[${index}][relationship]`, label: root.dataset.spouseMode === 'true' ? 'Spouse emergency contact relationship' : 'Relationship', required: false, placeholder: 'e.g. spouse, parent, friend' })}
                <div class="membership-field"><label class="membership-label">Country <span class="text-rose-600" aria-hidden="true">*</span></label><select name="emergency_contacts[${index}][country_iso2]" class="membership-control" required>${countryOptions(defaultCountry())}</select></div>
                ${fieldMarkup({ name: `emergency_contacts[${index}][phone]`, label: root.dataset.spouseMode === 'true' ? 'Spouse emergency contact phone number' : 'Phone number', type: 'tel', required: true, placeholder: 'Enter phone number' })}
                ${fieldMarkup({ name: `emergency_contacts[${index}][notes]`, label: 'Notes (optional)', type: 'textarea', required: false, full: true })}
            </div>`;
        row.querySelector('[data-remove-row]').addEventListener('click', () => { if (list.children.length <= 1) { showErrors(['At least one emergency contact is required.']); return; } row.remove(); });
        list.appendChild(row);
    };

    const buildForm = () => {
        const configuredExtensions = (config.steps || []).filter((step) => step.type === 'extension');
        steps = [
            { key: 'personal', label: root.dataset.spouseMode === 'true' ? 'Spouse Personal Details' : 'Personal details', type: 'core' },
            { key: 'address', label: 'Addresses', type: 'core' },
            ...configuredExtensions,
            { key: 'documents', label: 'Identification & documents', type: 'core' },
            { key: 'summary', label: 'Review & submit', type: 'core' },
        ];
        panels.innerHTML = '';
        steps.forEach((step, index) => {
            let content = '';
            if (step.key === 'personal') content = personalPanel();
            else if (step.key === 'address') content = addressPanel();
            else if (step.type === 'extension') content = extensionPanel(step.module);
            else if (step.key === 'documents') content = documentsPanel();
            else if (step.key === 'summary') content = summaryPanel();
            panels.insertAdjacentHTML('beforeend', panelShell(step, index, content));
        });
        renderStepper();
        updateNavigation();
        form.classList.remove('hidden');
        loading.classList.add('hidden');
        loadError.classList.add('hidden');

        const whatsappCountry = form.querySelector('[data-phone-country="whatsapp"]');
        const whatsappCode = form.querySelector('[data-calling-code="whatsapp"]');
        const updateWhatsappCode = () => { if (whatsappCode && whatsappCountry) whatsappCode.value = countryByIso(whatsappCountry.value)?.calling_code || ''; };
        whatsappCountry?.addEventListener('change', updateWhatsappCode);
        updateWhatsappCode();

        const emergencyList = form.querySelector('[data-emergency-list]');
        if (emergencyList && emergencyList.children.length === 0) addEmergencyRow();
        form.querySelector('[data-add-phone]')?.addEventListener('click', addPhoneRow);
        form.querySelector('[data-add-emergency]')?.addEventListener('click', addEmergencyRow);
        form.querySelector('[data-add-address]')?.addEventListener('click', addAddressRow);
        form.addEventListener('input', (event) => {
            const input = event.target;
            if (input instanceof HTMLInputElement && input.type === 'tel') syncPhoneCountryFromInput(input);
        });
        form.querySelectorAll('[name="spouse[needed]"]').forEach((radio) => radio.addEventListener('change', updateSpouseVisibility));
        form.querySelectorAll('[name="spouse[existing]"]').forEach((radio) => radio.addEventListener('change', updateSpouseVisibility));
        form.querySelector('[data-spouse-search]')?.addEventListener('input', searchSpouseMembers);
        form.querySelectorAll('input[type="date"][name="date_of_birth"]').forEach((input) => { input.max = todayIso(); });
        // Restore any saved draft first. In spouse mode, the source applicant's
        // draft must never overwrite the explicit spouse prefill below.
        applyDraftToForm();
        if (root.dataset.spouseMode !== 'true') {
            const fullName = String(root.dataset.userName || '').trim().replace(/\s+/g, ' ');
            const parts = fullName ? fullName.split(' ') : [];
            if (parts.length) {
                const nameParts = {
                    first_name: parts[0] || '',
                    middle_name: parts.length > 2 ? parts.slice(1, -1).join(' ') : '',
                    last_name: parts.length > 1 ? parts[parts.length - 1] : '',
                };
                Object.entries(nameParts).forEach(([key, value]) => {
                    const control = form.querySelector(`[name="${key}"]`);
                    if (control && !String(control.value || '').trim() && value) control.value = value;
                });
            }
        }
        if (root.dataset.spouseMode === 'true') {
            try {
                const prefill = JSON.parse(root.dataset.spousePrefill || '{}');
                // Backwards compatibility with the earlier addresses-only payload.
                const addresses = Array.isArray(prefill) ? prefill : (prefill.addresses || []);
                addresses.slice(0, 2).forEach((address, index) => {
                    Object.entries(address || {}).forEach(([key, value]) => {
                        const control = form.querySelector(`[name="addresses[${index}][${key}]"]`);
                        if (control && value != null) control.value = String(value);
                    });
                });

                if (!Array.isArray(prefill)) {
                    if (prefill.preferred_unit_id != null) {
                        const unit = form.querySelector('[name="preferred_unit_id"]');
                        if (unit) unit.value = String(prefill.preferred_unit_id);
                    }
                    if (prefill.gender) {
                        const gender = form.querySelector('[name="gender"]');
                        if (gender) gender.value = String(prefill.gender);
                    }
                    const contacts = Array.isArray(prefill.emergency_contacts) ? prefill.emergency_contacts : [];
                    const list = form.querySelector('[data-emergency-list]');
                    if (list && contacts.length) {
                        list.innerHTML = '';
                        emergencyRowSequence = 0;
                        contacts.slice(0, 5).forEach((contact, index) => {
                            addEmergencyRow();
                            Object.entries(contact || {}).forEach(([key, value]) => {
                                const control = form.querySelector(`[name="emergency_contacts[${index}][${CSS.escape(key)}]"]`);
                                if (control && value != null) control.value = String(value);
                            });
                        });
                    }
                }
            } catch (_) { /* Prefill is optional; applicant can enter addresses manually. */ }
        }
        bindFileInputs();
        if (draft && root.dataset.spouseMode !== 'true') {
            const savedStep = Number(draft.current_step || 2);
            currentStep = Math.min(Math.max(0, savedStep - 1), steps.length - 1);
        }
        updateSpouseVisibility();
        renderStepper();
        updateNavigation();
        renderSummary();
    };

    let extraAddressSequence = 2;
    const addAddressRow = () => {
        const list = panels.querySelector('[data-address-list]');
        if (!list || list.querySelectorAll('[data-address-card]').length >= 6) {
            showErrors(['You can add up to 6 addresses.']);
            return;
        }
        const type = `additional_${extraAddressSequence}`;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = addressFieldsMarkup(type, 'Additional address', extraAddressSequence, false);
        const card = wrapper.firstElementChild;
        card.querySelector('[data-remove-address]')?.addEventListener('click', () => card.remove());
        list.appendChild(card);
        extraAddressSequence += 1;
    };

    const updateSpouseVisibility = () => {
        const needed = form.querySelector('[name="spouse[needed]"]:checked')?.value === 'yes';
        const existing = form.querySelector('[name="spouse[existing]"]:checked')?.value;
        const options = form.querySelector('[data-spouse-options]');
        const searchWrap = form.querySelector('[data-spouse-search-wrap]');
        const newNote = form.querySelector('[data-spouse-new-note]');
        options?.classList.toggle('hidden', !needed);
        searchWrap?.classList.toggle('hidden', !needed || existing !== 'yes');
        newNote?.classList.toggle('hidden', !needed || existing !== 'no');
        if (!needed) {
            const memberId = form.querySelector('[data-spouse-member-id]');
            if (memberId) memberId.value = '';
        }
    };

    let spouseSearchSequence = 0;
    const searchSpouseMembers = async (event) => {
        const query = event.target.value.trim();
        const results = form.querySelector('[data-spouse-results]');
        const sequence = ++spouseSearchSequence;
        results.innerHTML = '';
        form.querySelector('[data-spouse-member-id]').value = '';
        if (query.length < 2) return;
        const item = document.createElement('p');
        item.className = 'text-sm text-slate-500';
        item.textContent = 'Searching members…';
        results.appendChild(item);
        try {
            const url = new URL(root.dataset.spouseSearchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await response.json();
            if (sequence !== spouseSearchSequence) return;
            results.innerHTML = '';
            const members = body.data || [];
            if (!members.length) {
                const empty = document.createElement('p'); empty.className = 'rounded-xl bg-stone-50 p-3 text-sm text-slate-600'; empty.textContent = 'No matching members found. Choose “No, register spouse” if they are not a member.'; results.appendChild(empty); return;
            }
            members.forEach((member) => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'flex w-full items-center gap-3 rounded-xl border border-stone-200 p-3 text-left hover:bg-stone-50';
                const photo = document.createElement('img'); photo.className = 'h-11 w-11 rounded-full bg-stone-100 object-cover'; photo.alt = ''; photo.src = member.photo_url || '/images/profile-placeholder.png';
                const info = document.createElement('span'); info.className = 'min-w-0 flex-1';
                const name = document.createElement('span'); name.className = 'block truncate text-sm font-semibold text-slate-900'; name.textContent = member.name;
                const email = document.createElement('span'); email.className = 'block truncate text-xs text-slate-500'; email.textContent = member.email || '';
                info.append(name, email); button.append(photo, info);
                button.addEventListener('click', () => {
                    form.querySelector('[data-spouse-member-id]').value = member.id;
                    results.innerHTML = '';
                    const chosen = document.createElement('div'); chosen.className = 'flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3';
                    const selectedName = document.createElement('p'); selectedName.className = 'text-sm font-semibold text-emerald-900'; selectedName.textContent = `Selected: ${member.name} (${member.email || 'no email'})`;
                    const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'ml-auto text-sm font-semibold text-emerald-800'; clear.textContent = 'Change'; clear.addEventListener('click', () => { form.querySelector('[data-spouse-member-id]').value = ''; searchSpouseMembers({ target: form.querySelector('[data-spouse-search]') }); });
                    chosen.append(selectedName, clear); results.appendChild(chosen);
                });
                results.appendChild(button);
            });
        } catch (_) {
            if (sequence === spouseSearchSequence) { results.innerHTML = '<p class="text-sm text-rose-700">Could not search members. Please try again.</p>'; }
        }
    };

    const renderSummary = () => {
        const summary = form.querySelector('[data-application-summary]');
        if (!summary) return;
        const pairs = [];
        form.querySelectorAll('input, select, textarea').forEach((control) => {
            if (control.disabled || control.type === 'hidden' || control.name.startsWith('spouse[')) return;
            if (['button', 'submit', 'reset', 'password'].includes(control.type)) return;
            if (['radio', 'checkbox'].includes(control.type) && !control.checked) return;
            let value = '';
            if (control.type === 'file') value = control.files?.[0]?.name || 'Not selected';
            else if (control.tagName === 'SELECT') value = control.selectedOptions?.[0]?.textContent?.trim() || '';
            else value = String(control.value || '').trim();
            if (!value) return;
            const label = control.id ? form.querySelector(`label[for="${CSS.escape(control.id)}"]`) : control.closest('.membership-field')?.querySelector('.membership-label');
            const labelText = (label?.textContent || control.getAttribute('aria-label') || humanize(control.name.replace(/\[\d+\]/g, '').replace(/[\[\]]/g, ' '))).replace(/\*/g, '').trim();
            pairs.push([labelText, value]);
        });
        if (root.dataset.spouseMode !== 'true') {
            const needed = form.querySelector('[name="spouse[needed]"]:checked')?.value === 'yes';
            const existing = form.querySelector('[name="spouse[existing]"]:checked')?.value;
            let spouseSummary = 'No';
            if (needed && existing === 'yes') {
                const selected = form.querySelector('[data-spouse-results] .text-emerald-900')?.textContent || 'Existing member selected';
                spouseSummary = selected;
            } else if (needed && existing === 'no') spouseSummary = 'Yes — separate spouse registration after submission';
            pairs.push(['Add spouse', spouseSummary]);
        }
        summary.innerHTML = pairs.map(([label, value]) => `<div class="min-w-0 rounded-xl border border-stone-100 bg-white p-3"><dt class="text-xs font-medium text-slate-500">${escapeHtml(label)}</dt><dd class="mt-1 break-words text-sm font-semibold text-slate-900">${escapeHtml(value)}</dd></div>`).join('');
    };

    const validateCurrentPanel = () => {
        const panel = form.querySelector(`[data-step-panel="${CSS.escape(steps[currentStep].key)}"]`);
        const controls = [...panel.querySelectorAll('input, select, textarea')].filter((control) => !control.disabled);
        if (steps[currentStep]?.key === 'personal') {
            const emergencyRows = [...panel.querySelectorAll('[data-emergency-list] > div')];
            const hasCompleteEmergencyContact = emergencyRows.some((row) =>
                Boolean(row.querySelector('[name$="[contact_name]"]')?.value.trim())
                && Boolean(row.querySelector('[name$="[phone]"]')?.value.trim())
                && Boolean(row.querySelector('[name$="[country_iso2]"]')?.value)
            );
            if (!hasCompleteEmergencyContact) {
                showErrors(['Add at least one complete emergency contact before continuing.']);
                const firstName = panel.querySelector('[data-emergency-list] [name$="[contact_name]"]');
                firstName?.focus({ preventScroll: true });
                firstName?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }
        for (const control of controls) {
            if (control.name === 'date_of_birth' && control.value && control.value > todayIso()) {
                control.setCustomValidity('Date of birth cannot be in the future.');
            } else {
                control.setCustomValidity('');
            }
            if (control.type === 'file' && control.name === 'profile_photo' && control.files?.[0]) {
                const file = control.files[0];
                if (!['image/jpeg', 'image/png'].includes(file.type)) control.setCustomValidity('Please upload a JPG or PNG image.');
                else if (file.size > 5 * 1024 * 1024) control.setCustomValidity('The profile photo must be 5 MB or smaller.');
                else control.setCustomValidity('');
            }
            if (!control.checkValidity()) {
                control.reportValidity();
                control.focus({ preventScroll: true });
                control.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }
        if (steps[currentStep].key === 'summary' && root.dataset.spouseMode !== 'true') {
            const spouseChoice = form.querySelector('[name="spouse[needed]"]:checked')?.value;
            const questionSection = form.querySelector('[data-spouse-question-section]');
            const questionError = form.querySelector('[data-spouse-question-error]');
            if (!spouseChoice) {
                questionError?.classList.remove('hidden');
                showErrors(['Please select whether you want to add your spouse before submitting.']);
                questionSection?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                form.querySelector('[name="spouse[needed]"]')?.focus({ preventScroll: true });
                return false;
            }
            questionError?.classList.add('hidden');
            const needed = spouseChoice === 'yes';
            const existing = form.querySelector('[name="spouse[existing]"]:checked')?.value;
            if (needed && !existing) { showErrors(['Please tell us whether your spouse is already a member.']); form.querySelector('[data-spouse-options]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); return false; }
            if (needed && existing === 'yes' && !form.querySelector('[data-spouse-member-id]').value) { showErrors(['Please search for and select your spouse.']); return false; }
        }
        return true;
    };

    const showErrors = (messages) => {
        errorList.innerHTML = '';
        [...new Set(messages)].forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            errorList.appendChild(item);
        });
        errorBox.classList.toggle('hidden', errorList.children.length === 0);
        if (errorList.children.length) errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const stepForError = (key) => {
        if (key.startsWith('address.') || key.startsWith('addresses.')) return steps.findIndex((step) => step.key === 'address');
        if (key.startsWith('identification') || key.startsWith('identifications')) return steps.findIndex((step) => step.key === 'documents');
        if (key.startsWith('spouse') || key === 'source_application_id') return steps.findIndex((step) => step.key === 'summary');
        if (key.startsWith('extension_data.')) {
            const module = key.split('.')[1];
            return steps.findIndex((step) => step.key === `extension.${module}`);
        }
        if (key.startsWith('identification') || key.startsWith('identifications')) return steps.findIndex((step) => step.key === 'documents');
        if (key.startsWith('spouse') || key === 'source_application_id') return steps.findIndex((step) => step.key === 'summary');
        return steps.findIndex((step) => step.key === 'personal');
    };

    const buildSubmissionData = () => {
        const data = new FormData(form);
        if (root.dataset.spouseMode === 'true') {
            data.append('source_application_id', new URL(window.location.href).searchParams.get('source_application') || '');
            data.append('spouse_registration', '1');
        }
        let phoneIndex = 0;
        const whatsapp = form.querySelector('[name="whatsapp"]')?.value.trim() || '';
        const whatsappCountry = form.querySelector('[data-phone-country="whatsapp"]')?.value || defaultCountry();
        if (whatsapp) {
            data.append(`phone_numbers[${phoneIndex}][type]`, 'whatsapp');
            data.append(`phone_numbers[${phoneIndex}][phone]`, whatsapp);
            data.append(`phone_numbers[${phoneIndex}][country_iso2]`, whatsappCountry);
            phoneIndex += 1;
        }
        const home = form.querySelector('[name="home_contact"]')?.value.trim() || '';
        const homeCountry = form.querySelector('[data-phone-country="home_contact"]')?.value || defaultCountry();
        if (home) {
            data.append(`phone_numbers[${phoneIndex}][type]`, 'home');
            data.append(`phone_numbers[${phoneIndex}][phone]`, home);
            data.append(`phone_numbers[${phoneIndex}][country_iso2]`, homeCountry);
            phoneIndex += 1;
        }
        panels.querySelectorAll('[data-phone-list] > div').forEach((row) => {
            const type = row.querySelector('[data-extra-phone-type]')?.value || 'alternate';
            const phone = row.querySelector('[data-extra-phone-number]')?.value.trim() || '';
            const country = row.querySelector('[data-extra-phone-country]')?.value || defaultCountry();
            if (!phone) return;
            data.append(`phone_numbers[${phoneIndex}][type]`, type);
            data.append(`phone_numbers[${phoneIndex}][phone]`, phone);
            data.append(`phone_numbers[${phoneIndex}][country_iso2]`, country);
            phoneIndex += 1;
        });
        return data;
    };

    const applyDraftToForm = () => {
        if (!draft?.step_1) return;
        const values = draft.step_1;
        Object.entries(values).forEach(([key, value]) => {
            if (['emergency_contacts', 'phone_numbers', 'profile_photo'].includes(key)) return;
            const control = form.querySelector(`[name="${CSS.escape(key)}"]`);
            if (control && value !== null && value !== undefined && !control.type?.includes('file')) control.value = String(value);
        });
        const emergencyList = form.querySelector('[data-emergency-list]');
        const contacts = Array.isArray(values.emergency_contacts) ? values.emergency_contacts : [];
        if (emergencyList && contacts.length) {
            emergencyList.innerHTML = '';
            // addEmergencyRow uses a sequence to generate field names. Reset it
            // after removing the initial placeholder row so saved index 0 maps
            // back to emergency_contacts[0][...], not index 1.
            emergencyRowSequence = 0;
            contacts.slice(0, 5).forEach((contact, index) => {
                addEmergencyRow();
                Object.entries(contact || {}).forEach(([key, value]) => {
                    const control = form.querySelector(`[name="emergency_contacts[${index}][${CSS.escape(key)}]"]`);
                    if (control && value != null) control.value = String(value);
                });
            });
        }
        const photoInput = form.querySelector('[data-profile-photo]');
        if (draft.profile_photo_url && photoInput) {
            photoInput.required = false;
            const preview = photoInput.closest('.membership-field')?.querySelector('[data-file-preview]');
            const image = preview?.querySelector('[data-file-preview-image]');
            const name = preview?.querySelector('[data-file-preview-name]');
            preview?.classList.remove('hidden');
            image?.classList.remove('hidden');
            if (image) image.src = draft.profile_photo_url;
            if (name) name.textContent = 'Saved profile photo';
        }
        if (Array.isArray(values.phone_numbers)) {
            const seenPhoneEntries = new Set();
            values.phone_numbers.forEach((phone) => {
                const type = String(phone?.type || 'alternate').toLowerCase();
                if (['whatsapp', 'home'].includes(type)) return;
                const number = String(phone?.phone || '').trim();
                if (!number) return;
                const country = String(phone?.country_iso2 || defaultCountry()).toUpperCase();
                const signature = `${type}|${country}|${number.replace(/\D/g, '')}`;
                if (seenPhoneEntries.has(signature)) return;
                seenPhoneEntries.add(signature);
                const row = document.createElement('div');
                row.className = 'rounded-2xl border border-stone-200 p-3';
                row.innerHTML = `<div class="grid grid-cols-1 gap-3 sm:grid-cols-3"><div><label class="membership-label">Type</label><select class="membership-control" data-extra-phone-type>${['alternate','work','other'].map((optionType) => `<option value="${optionType}" ${type === optionType ? 'selected' : ''}>${humanize(optionType)}</option>`).join('')}</select></div><div><label class="membership-label">Country</label><select class="membership-control" data-extra-phone-country>${countryOptions(country)}</select></div><div><label class="membership-label">Phone number</label><input class="membership-control" data-extra-phone-number type="tel" value="${escapeHtml(number)}"></div></div>`;
                panels.querySelector('[data-phone-list]')?.appendChild(row);
            });
        }

        const savedAddresses = draft?.step_2?.addresses;
        if (Array.isArray(savedAddresses)) {
            const addressList = panels.querySelector('[data-address-list]');
            savedAddresses.slice(0, 6).forEach((address, index) => {
                while (addressList && addressList.querySelectorAll('[data-address-card]').length <= index) addAddressRow();
                Object.entries(address || {}).forEach(([key, value]) => {
                    const control = form.querySelector(`[name="addresses[${index}][${CSS.escape(key)}]"]`);
                    if (control && value !== null && value !== undefined) control.value = String(value);
                });
            });
        }
        const homeContact = form.querySelector('[name="home_contact"]');
        if (homeContact && draft?.step_2 && draft.step_2.home_contact != null) homeContact.value = String(draft.step_2.home_contact);
        const homeContactCountry = form.querySelector('[data-phone-country="home_contact"]');
        if (homeContactCountry && draft?.step_2?.home_contact_country_iso2) homeContactCountry.value = String(draft.step_2.home_contact_country_iso2).toUpperCase();

        const applyNestedValues = (value, prefix = '') => {
            if (!value || typeof value !== 'object') return;
            Object.entries(value).forEach(([key, child]) => {
                const name = prefix ? `${prefix}[${key}]` : key;
                if (child && typeof child === 'object' && !Array.isArray(child)) {
                    applyNestedValues(child, name);
                    return;
                }
                if (Array.isArray(child)) {
                    child.forEach((item, index) => {
                        if (item && typeof item === 'object') applyNestedValues(item, `${name}[${index}]`);
                        else applyControlValue(`${name}[${index}]`, item);
                    });
                    return;
                }
                applyControlValue(name, child);
            });
        };
        const applyControlValue = (name, value) => {
            const controls = Array.from(form.querySelectorAll('[name]')).filter((control) => control.name === name);
            controls.forEach((control) => {
                if (value === null || value === undefined || control.type === 'file') return;
                if (control.type === 'radio' || control.type === 'checkbox') control.checked = String(control.value) === String(value) || (control.type === 'checkbox' && Boolean(value));
                else control.value = String(value);
            });
        };
        for (let stepNumber = 3; stepNumber <= 5; stepNumber += 1) {
            applyNestedValues(draft?.[`step_${stepNumber}`] || {});
        }

        // File inputs cannot be repopulated by the browser. Show a private
        // preview/link for proof documents already stored with the draft.
        (draft?.identification_documents || []).forEach((savedDocument) => {
            const inputName = `identification_documents[${savedDocument.code}][${savedDocument.side}]`;
            const input = Array.from(form.querySelectorAll('input[type="file"][name]'))
                .find((control) => control.name === inputName);
            if (!input) return;
            input.required = false;
            const preview = input.closest('.membership-field')?.querySelector('[data-file-preview]');
            const image = preview?.querySelector('[data-file-preview-image]');
            const pdf = preview?.querySelector('[data-file-preview-pdf]');
            const name = preview?.querySelector('[data-file-preview-name]');
            preview?.classList.remove('hidden');
            if (savedDocument.mime_type && savedDocument.mime_type.startsWith('image/')) {
                image?.classList.remove('hidden');
                if (image) image.src = savedDocument.url;
                pdf?.classList.add('hidden');
            } else {
                image?.classList.add('hidden');
                pdf?.classList.remove('hidden');
            }
            if (name) name.textContent = `Saved proof: ${savedDocument.file_name || 'document'}`;
        });
    };

    const saveFirstStep = async () => {
        if (root.dataset.spouseMode === 'true') return true;
        const response = await fetch(root.dataset.draftUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            body: buildSubmissionData(),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const entries = Object.entries(body.errors || {});
            if (entries.length) showErrors(entries.flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages]).map((message) => `${humanize(field.replace(/\./g, ' '))}: ${message}`)));
            else showErrors([body.message || 'We could not save Step 1. Please try again.']);
            return false;
        }
        draft = { ...(draft || {}), id: body.application_id, current_step: 2, profile_photo_url: body.profile_photo_url, step_1: body.step_1 || draft?.step_1 || {} };
        const photoInput = form.querySelector('[data-profile-photo]');
        if (photoInput && body.profile_photo_url) {
            photoInput.required = false;
            const preview = photoInput.closest('.membership-field')?.querySelector('[data-file-preview]');
            const image = preview?.querySelector('[data-file-preview-image]');
            const name = preview?.querySelector('[data-file-preview-name]');
            preview?.classList.remove('hidden'); image?.classList.remove('hidden');
            if (image) image.src = body.profile_photo_url;
            if (name) name.textContent = 'Saved profile photo';
        }
        return true;
    };

    const saveAddressStep = async () => {
        if (root.dataset.spouseMode === 'true') return true;
        const addressPanel = form.querySelector('[data-step-panel="address"]');
        if (!addressPanel) return true;
        const data = new FormData();
        data.append('draft_step', '2');
        addressPanel.querySelectorAll('input[name], select[name], textarea[name]').forEach((control) => {
            if (control.disabled || control.type === 'file') return;
            if (['radio', 'checkbox'].includes(control.type) && !control.checked) return;
            data.append(control.name, control.value);
        });
        const homeContactCountry = addressPanel.querySelector('[data-phone-country="home_contact"]');
        if (homeContactCountry) data.append('home_contact_country_iso2', homeContactCountry.value || defaultCountry());

        try {
            const response = await fetch(root.dataset.draftUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: data,
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const entries = Object.entries(body.errors || {});
                if (entries.length) showErrors(entries.flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages]).map((message) => `${humanize(field.replace(/\./g, ' '))}: ${message}`)));
                else showErrors([body.message || 'We could not save Step 2. Please try again.']);
                return false;
            }
            draft = { ...(draft || {}), current_step: body.current_step || 3, step_2: body.step_2 || { addresses: [] } };
            return true;
        } catch (_) {
            showErrors(['We could not connect to the server while saving Step 2. Please try again.']);
            return false;
        }
    };

    const saveAdditionalStep = async (advance = false) => {
        if (root.dataset.spouseMode === 'true' || currentStep < 2 || steps[currentStep]?.key === 'summary') return true;
        const stepNumber = currentStep + 1;
        const panel = form.querySelector(`[data-step-panel="${CSS.escape(steps[currentStep].key)}"]`);
        if (!panel) return true;
        const data = new FormData();
        data.append('draft_step', String(stepNumber));
        data.append('advance_step', advance ? '1' : '0');
        panel.querySelectorAll('input[name], select[name], textarea[name]').forEach((control) => {
            if (control.disabled || ['button', 'submit', 'reset'].includes(control.type)) return;
            if (control.type === 'file') {
                if (control.files?.[0]) data.append(control.name, control.files[0]);
                return;
            }
            if (['radio', 'checkbox'].includes(control.type) && !control.checked) return;
            data.append(control.name, control.value);
        });
        try {
            const response = await fetch(root.dataset.draftUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: data,
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                showErrors([body.message || `We could not save Step ${stepNumber}. Please try again.`]);
                return false;
            }
            draft = {
                ...(draft || {}),
                current_step: body.current_step || draft?.current_step || stepNumber,
                [`step_${stepNumber}`]: body.step_data || draft?.[`step_${stepNumber}`] || {},
                identification_documents: body.identification_documents || draft?.identification_documents || [],
            };
            return true;
        } catch (_) {
            showErrors([`We could not connect to the server while saving Step ${stepNumber}. Please try again.`]);
            return false;
        }
    };

    const scheduleAdditionalStepSave = () => {
        if (currentStep < 2 || steps[currentStep]?.key === 'summary' || root.dataset.spouseMode === 'true') return;
        if (draftSaveTimer) clearTimeout(draftSaveTimer);
        draftSaveTimer = setTimeout(() => { saveAdditionalStep(false); }, 500);
    };

    const submit = async () => {
        if (submitting || !validateCurrentPanel()) return;
        submitting = true;
        updateNavigation();
        showErrors([]);
        try {
            const response = await fetch(root.dataset.submitUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: buildSubmissionData(),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = body.errors || {};
                const entries = Object.entries(errors);
                if (entries.length) {
                    const targetIndex = stepForError(entries[0][0]);
                    if (targetIndex >= 0) {
                        currentStep = targetIndex;
                        updateNavigation();
                    }
                    showErrors(entries.flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages]).map((message) => `${humanize(field.replace(/\./g, ' '))}: ${message}`)));
                } else {
                    showErrors([body.message || (response.status === 403 ? 'You do not have permission to submit an application for this organization.' : 'We could not submit your application. Please try again.')]);
                }
                return;
            }
            const spouseNeeded = form.querySelector('[name="spouse[needed]"]:checked')?.value === 'yes';
            const spouseExisting = form.querySelector('[name="spouse[existing]"]:checked')?.value;
            if (root.dataset.spouseMode !== 'true' && spouseNeeded && spouseExisting === 'no' && root.dataset.spouseRegistrationUrl) {
                const target = new URL(root.dataset.spouseRegistrationUrl, window.location.origin);
                target.searchParams.set('source_application', body.data?.id || body.id || '');
                window.location.assign(target.toString());
                return;
            }
            form.classList.add('hidden');
            success.classList.remove('hidden');
            const applicationReference = body.data?.id || body.id;
            if (applicationReference) success.querySelector('[data-success-message]').textContent = `Your application reference is ${applicationReference}. The organization will review it and follow up with you.`;
            success.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            showErrors(['We could not connect to the server. Check your internet connection and try again.']);
        } finally {
            submitting = false;
            updateNavigation();
        }
    };

    previousButton.addEventListener('click', () => {
        if (currentStep <= 0) return;
        currentStep -= 1;
        showErrors([]);
        updateNavigation();
        document.querySelector('h1')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    nextButton.addEventListener('click', async () => {
        if (!validateCurrentPanel()) return;
        if (currentStep < steps.length - 1) {
            if (currentStep === 0 && root.dataset.spouseMode !== 'true') {
                nextButton.disabled = true;
                nextButton.textContent = 'Saving…';
                const saved = await saveFirstStep();
                nextButton.disabled = false;
                if (!saved) { updateNavigation(); return; }
            }
            if (currentStep === 1 && root.dataset.spouseMode !== 'true') {
                nextButton.disabled = true;
                nextButton.textContent = 'Saving…';
                const saved = await saveAddressStep();
                nextButton.disabled = false;
                if (!saved) { updateNavigation(); return; }
            } else if (currentStep >= 2 && steps[currentStep]?.key !== 'summary' && root.dataset.spouseMode !== 'true') {
                if (draftSaveTimer) clearTimeout(draftSaveTimer);
                nextButton.disabled = true;
                nextButton.textContent = 'Saving…';
                const saved = await saveAdditionalStep(true);
                nextButton.disabled = false;
                if (!saved) { updateNavigation(); return; }
            }
            currentStep += 1;
            if (steps[currentStep]?.key === 'summary') renderSummary();
            showErrors([]);
            updateNavigation();
            const heading = form.querySelector(`[data-step-panel="${CSS.escape(steps[currentStep].key)}"] h2`);
            heading?.focus({ preventScroll: true });
            heading?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        await submit();
    });

    form.addEventListener('input', (event) => {
        if (event.target?.closest?.('[data-step-panel]')) scheduleAdditionalStepSave();
    });
    form.addEventListener('change', (event) => {
        if (event.target?.closest?.('[data-step-panel]')) scheduleAdditionalStepSave();
    });
    form.addEventListener('submit', (event) => { event.preventDefault(); nextButton.click(); });

    const loadConfiguration = async () => {
        loading.classList.remove('hidden');
        loadError.classList.add('hidden');
        form.classList.add('hidden');
        try {
            const response = await fetch(root.dataset.configUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || 'We could not access this organization’s application configuration.');
            config = body;
            draft = body.draft || null;
            buildForm();
        } catch (error) {
            loading.classList.add('hidden');
            loadError.classList.remove('hidden');
            loadError.querySelector('[data-load-error-message]').textContent = error.message || 'Please check your connection and try again.';
        }
    };

    loadError.querySelector('[data-retry]').addEventListener('click', loadConfiguration);
    loadConfiguration();
})();
