const issuedLinks = new Map();
let errorSequence = 0;

function notify(message, icon = 'success') {
    window.Swal.fire({ toast: true, position: 'bottom-end', icon, title: message, timer: icon === 'success' ? 4500 : undefined, showConfirmButton: icon !== 'success' });
}

function clearErrors(form) {
    form.querySelectorAll('[data-fp-error]').forEach(node => node.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(field => {
        field.removeAttribute('aria-invalid');
        const errorId = field.dataset.fpErrorId;
        if (errorId) {
            const ids = (field.getAttribute('aria-describedby') || '').split(' ').filter(id => id !== errorId);
            if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
            else field.removeAttribute('aria-describedby');
            delete field.dataset.fpErrorId;
        }
    });
}

function showErrors(form, errors, message) {
    if (form.hasAttribute('data-fp-assessment')) {
        const state = form.closest('[data-fp-root]').querySelector('[data-fp-assessment-state]');
        state.textContent = 'Penyimpanan belum berhasil. Input Anda tetap tersedia; periksa pesan kesalahan dan coba lagi.';
        state.className = 'rounded-xl bg-amber-50 p-4 text-amber-900';
    }
    let count = 0;
    for (const [key, messages] of Object.entries(errors || {})) {
        const field = Array.from(form.elements).find(element => {
            const name = (element.name || '').replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
            return name && (name === key || key.startsWith(name + '.'));
        });
        const error = document.createElement('p');
        error.dataset.fpError = '';
        error.className = 'mt-1 text-sm text-red-700';
        error.id = 'fp-error-' + (++errorSequence);
        error.textContent = messages.join(' ');
        if (field) {
            field.setAttribute('aria-invalid', 'true');
            field.dataset.fpErrorId = error.id;
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
            (field.closest('label') || field).insertAdjacentElement('afterend', error);
        } else {
            form.append(error);
        }
        count++;
    }
    if (!count) {
        const error = document.createElement('p');
        error.dataset.fpError = '';
        error.className = 'mt-2 text-sm text-red-700';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        form.append(error);
    }
    notify(message, 'error');
}

function updatePicker(root) {
    const assigned = new Set(Array.from(root.querySelectorAll('[data-fp-pic-id]')).map(row => row.dataset.fpPicId));
    root.querySelectorAll('[data-fp-user-option] input').forEach(input => {
        input.disabled = assigned.has(input.value);
        if (input.disabled) input.checked = false;
    });
}

function linkPanel(url) {
    const panel = document.createElement('div');
    panel.dataset.fpLinkPanel = '';
    panel.className = 'mt-3 w-full rounded-lg border border-amber-200 bg-amber-50 p-3';
    const label = document.createElement('label');
    label.className = 'block text-sm font-semibold';
    label.textContent = 'Tautan penguji — simpan sebelum meninggalkan halaman';
    const input = document.createElement('input');
    input.type = 'url';
    input.readOnly = true;
    input.value = url;
    input.className = 'mt-2 w-full rounded-lg border-slate-300 text-sm';
    label.append(input);
    const copy = document.createElement('button');
    copy.type = 'button';
    copy.dataset.fpCopy = '';
    copy.className = 'mt-2 rounded-lg border border-amber-300 px-3 py-2 text-sm';
    copy.textContent = 'Salin tautan';
    const help = document.createElement('p');
    help.className = 'mt-2 text-xs text-slate-600';
    help.textContent = 'Berlaku 14 hari dan hanya aktif ketika Berjalan. Terbitkan ulang hanya jika tautan hilang; tautan lama akan dibatalkan.';
    panel.append(label, copy, help);
    return panel;
}

function updateFragments(root, html, data) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const selector = data.structure_changed ? '[data-fp-structure], [data-fp-fragment="progres"], [data-fp-fragment="rekap"], [data-fp-fragment="hasil"], [data-fp-fragment="riwayat"]' : '[data-fp-fragment]';
    for (const source of doc.querySelectorAll(selector)) {
        const target = Array.from(root.querySelectorAll(selector)).find(node => (node.dataset.fpStructure || node.dataset.fpFragment) === (source.dataset.fpStructure || source.dataset.fpFragment));
        if (!target) continue;
        const openDetails = Array.from(target.querySelectorAll('details')).map(node => node.open);
        target.replaceChildren(...Array.from(source.childNodes).map(node => document.importNode(node, true)));
        target.querySelectorAll('details').forEach((node, index) => { node.open = openDetails[index] || false; });
    }
    if (data.invalidate_links) issuedLinks.clear();
    if (data.examiner_link) issuedLinks.set(String(data.examiner_id), data.examiner_link);
    if (data.revoked_examiner_id) issuedLinks.delete(String(data.revoked_examiner_id));
    for (const [id, url] of issuedLinks) {
        const row = Array.from(root.querySelectorAll('[data-fp-examiner-id]')).find(node => node.dataset.fpExaminerId === id);
        if (row) row.append(linkPanel(url));
        else issuedLinks.delete(id);
    }
    updatePicker(root);
}

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-fp-async]')) return;
    event.preventDefault();
    const root = form.closest('[data-fp-root]');
    if (!root || root.dataset.fpBusy === 'true') return;
    root.dataset.fpBusy = 'true';
    const buttons = Array.from(form.querySelectorAll('button'));
    const originalDisabled = buttons.map(button => button.disabled);
    const focusTarget = event.submitter;
    const section = form.closest('section');
    try {
        if (form.dataset.fpConfirm) {
            const confirmation = await window.Swal.fire({ title: form.dataset.fpConfirm, icon: 'warning', showCancelButton: true, confirmButtonText: 'Lanjutkan', cancelButtonText: 'Batal', focusCancel: true, returnFocus: false });
            if (!confirmation.isConfirmed) return;
        }
        clearErrors(form);
        if (form.hasAttribute('data-fp-assessment')) {
            form.querySelector('input[name="intent"]').value = event.submitter?.dataset.fpIntent || 'draft';
        }
        const body = new FormData(form);
        buttons.forEach(button => { button.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        const loading = document.createElement('p');
        loading.dataset.fpLoading = '';
        loading.className = 'mt-2 text-sm text-slate-500';
        loading.setAttribute('role', 'status');
        loading.textContent = 'Menyimpan…';
        form.append(loading);
        const response = await fetch(form.action, {
            method: form.method.toUpperCase(),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body, credentials: 'same-origin',
        });
        const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        loading.remove();
        if (!response.ok || !data) {
            showErrors(form, data?.errors, data?.message || (response.status === 419 ? 'Sesi kedaluwarsa. Salin input Anda sebelum memuat ulang halaman.' : 'Penyimpanan gagal. Periksa koneksi dan coba lagi.'));
            return;
        }
        if (data.redirect) {
            window.location.assign(data.redirect);
            return;
        }
        if (data.assessment_saved === true && form.hasAttribute('data-fp-assessment')) {
            const state = root.querySelector('[data-fp-assessment-state]');
            state.textContent = data.submitted ? 'Penilaian telah dikirim dan terkunci.' : 'Draft penilaian disimpan. Anda dapat menutup dan membuka kembali tautan ini.';
            state.className = 'rounded-xl bg-emerald-50 p-4 text-emerald-900';
            if (data.submitted) form.querySelector('[data-fp-assessment-fields]').disabled = true;
            notify(data.message);
            return;
        }
        if (typeof data.html !== 'string') throw new Error('Respons server tidak lengkap.');
        const anchor = form.isConnected ? form : section;
        const previousTop = anchor?.getBoundingClientRect().top;
        const overflowAnchor = root.style.overflowAnchor;
        root.style.overflowAnchor = 'none';
        updateFragments(root, data.html, data);
        if (form.hasAttribute('data-fp-reset')) {
            form.reset();
            updatePicker(root);
            form.querySelector('[data-fp-user-search]')?.dispatchEvent(new Event('input', { bubbles: true }));
        }
        notify(data.message);
        if (anchor?.isConnected && Number.isFinite(previousTop)) {
            window.scrollBy({ top: anchor.getBoundingClientRect().top - previousTop, behavior: 'instant' });
        }
        requestAnimationFrame(() => { root.style.overflowAnchor = overflowAnchor; });
    } catch {
        showErrors(form, {}, 'Tidak dapat menyimpan. Input tetap tersedia; periksa koneksi dan coba lagi.');
    } finally {
        root.dataset.fpBusy = 'false';
        form.removeAttribute('aria-busy');
        form.querySelector('[data-fp-loading]')?.remove();
        buttons.forEach((button, index) => { button.disabled = originalDisabled[index]; });
        if (focusTarget?.isConnected) focusTarget.focus({ preventScroll: true });
        else if (section?.isConnected) {
            const heading = section.querySelector('h2');
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
        }
    }
});

document.addEventListener('input', event => {
    const assessment = event.target.closest('form[data-fp-assessment]');
    if (assessment && !assessment.querySelector('[data-fp-assessment-fields]').disabled) {
        const state = assessment.closest('[data-fp-root]').querySelector('[data-fp-assessment-state]');
        state.textContent = 'Perubahan belum disimpan.';
        state.className = 'rounded-xl bg-amber-50 p-4 text-amber-900';
    }
    if (!event.target.matches('[data-fp-user-search]')) return;
    const term = event.target.value.trim().toLocaleLowerCase();
    event.target.closest('[data-fp-user-picker]').querySelectorAll('[data-fp-user-option]').forEach(option => {
        option.style.display = option.textContent.toLocaleLowerCase().includes(term) ? '' : 'none';
    });
});

document.addEventListener('click', async event => {
    const button = event.target.closest('[data-fp-copy]');
    if (!button) return;
    const input = button.closest('[data-fp-link-panel]').querySelector('input');
    try {
        if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(input.value);
        else {
            const x = window.scrollX, y = window.scrollY;
            input.focus({ preventScroll: true });
            input.select();
            if (!document.execCommand('copy')) throw new Error('Clipboard unavailable');
            button.focus({ preventScroll: true });
            window.scrollTo({ left: x, top: y, behavior: 'instant' });
        }
        notify('Tautan berhasil disalin.');
    } catch {
        input.focus({ preventScroll: true });
        input.select();
        notify('Salin URL pada kotak tautan secara manual.', 'info');
    }
});


// Preview partial input only; final completeness and calculations remain server-side.
function previewFitProper(article) {
    const fields = Array.from(article.querySelectorAll('[data-fp-indicator]'));
    const values = Object.fromEntries(fields.map(field => {
        const value = field.value.trim() === '' ? null : Number(field.value);
        return [field.dataset.fpIndicator, value !== null && Number.isFinite(value) && value >= 1 && value <= 5 && field.validity.valid ? value : null];
    }));
    const mean = entries => {
        const filled = entries.filter(value => value !== null && value !== undefined);
        return filled.length ? filled.reduce((sum, value) => sum + value, 0) / filled.length : null;
    };
    const core = mean(['adaptability', 'authenticity', 'contributing', 'learning', 'diversity'].map(key => values[key]));
    const eligibility = mean(['anti_bribery', 'anti_gratuity', 'conflict'].map(key => values[key]));
    const average = mean([core, values.job_mastery, values.improvement, eligibility]);
    for (const [key, value] of Object.entries({ core, eligibility, average })) {
        article.querySelector(`[data-fp-result="${key}"]`).value = value === null ? '-' : value.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    }
}

function initializeFitProperPreviews() {
    document.querySelectorAll('[data-fp-candidates] [data-participant-id]').forEach(previewFitProper);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFitProperPreviews, { once: true });
} else {
    initializeFitProperPreviews();
}

document.addEventListener('input', event => {
    if (!event.target.matches('[data-fp-indicator]')) return;
    const article = event.target.closest('[data-fp-candidates] [data-participant-id]');
    if (article) previewFitProper(article);
});
