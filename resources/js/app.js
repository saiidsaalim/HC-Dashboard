

import Alpine from 'alpinejs';
import { gsap } from 'gsap';
import './fit-proper';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

function initializeDashboardAnimations() {
    const cards = [...document.querySelectorAll('[data-dashboard-card]')];
    const counters = [...document.querySelectorAll('[data-dashboard-counter]')];

    if (cards.length === 0 && counters.length === 0) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const counterValues = counters.map((counter) => Number(counter.dataset.dashboardCounter));

    const renderCounter = (counter, value) => {
        counter.textContent = Math.round(value).toLocaleString('id-ID');
    };

    counters.forEach((counter, index) => {
        const finalValue = counterValues[index];

        if (!Number.isFinite(finalValue)) {
            return;
        }

        if (reduceMotion) {
            renderCounter(counter, finalValue);
            return;
        }

        const counterState = { value: 0 };

        gsap.to(counterState, {
            value: finalValue,
            duration: 0.8,
            delay: 0.2 + (index * 0.12),
            ease: 'power2.out',
            onUpdate: () => renderCounter(counter, counterState.value),
            onComplete: () => renderCounter(counter, finalValue),
        });
    });

    if (reduceMotion || cards.length === 0) {
        return;
    }

    gsap.from(cards, {
        opacity: 0,
        y: 30,
        scale: 0.96,
        duration: 0.7,
        stagger: 0.12,
        ease: 'power3.out',
        clearProps: 'transform,opacity',
    });
}

document.addEventListener('DOMContentLoaded', initializeDashboardAnimations);

async function confirmFormAction(form) {
    if (form.dataset.confirmPending === 'true' || form.dataset.confirmSubmitting === 'true') {
        return false;
    }

    const requiredCheckboxSelector = form.dataset.confirmRequireCheckbox;
    if (requiredCheckboxSelector && !document.querySelector(`${requiredCheckboxSelector}:checked`)) {
        await Swal.fire({
            icon: 'info',
            title: form.dataset.confirmEmptyTitle ?? 'Pilih data terlebih dahulu',
            text: form.dataset.confirmEmptyText ?? 'Centang minimal satu data.',
        });

        return false;
    }

    form.dataset.confirmPending = 'true';

    const result = await Swal.fire({
        title: form.dataset.confirmTitle,
        text: form.dataset.confirmText,
        icon: form.dataset.confirmIcon ?? 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButtonText ?? 'Ya, hapus',
        cancelButtonText: form.dataset.cancelButtonText ?? 'Batal',
        confirmButtonColor: form.dataset.confirmButtonColor ?? '#dc2626',
        cancelButtonColor: form.dataset.cancelButtonColor ?? '#64748b',
        reverseButtons: true,
        focusCancel: true,
    });

    delete form.dataset.confirmPending;

    return result.isConfirmed;
}

window.confirmFormAction = confirmFormAction;

async function submitAfterConfirmation(event) {
    event.preventDefault();
    const form = this;
    const confirmed = await confirmFormAction(form);

    if (!confirmed) {
        return;
    }

    form.dataset.confirmSubmitting = 'true';
    form.querySelectorAll('[type="submit"]').forEach((button) => {
        button.disabled = true;
    });
    form.submit();
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-submit]')) {
        return;
    }

    void submitAfterConfirmation.call(form, event);
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.matches('[data-confirm-submit]')) {
        return;
    }

    queueMicrotask(() => {
        if (event.defaultPrevented || form.dataset.submitting === 'true') {
            return;
        }

        form.dataset.submitting = 'true';
        form.classList.add('is-submitting');
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
        form.classList.remove('is-submitting');
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[type="submit"]').forEach((button) => {
            button.disabled = false;
        });
    });
});

document.querySelectorAll('dialog').forEach((dialog, index) => {
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('role', 'dialog');

    if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
        const heading = dialog.querySelector('h1, h2, h3');

        if (heading instanceof HTMLElement) {
            heading.id ||= `dialog-title-${index + 1}`;
            dialog.setAttribute('aria-labelledby', heading.id);
        }
    }
});

document.querySelectorAll('[data-employee-account-form]').forEach((form) => {
    const employeeSelect = form.querySelector('[data-employee-account-select]');
    const nameInput = form.elements.namedItem('name');
    const emailInput = form.elements.namedItem('email');
    const passwordInput = form.elements.namedItem('password');
    const roleSelect = form.elements.namedItem('role');
    const provisionsUser = form.hasAttribute('data-provision-user-form');

    if (!(employeeSelect instanceof HTMLSelectElement)
        || !(nameInput instanceof HTMLInputElement)
        || !(emailInput instanceof HTMLInputElement)) {
        return;
    }

    const manualIdentity = {
        name: nameInput.value,
        email: emailInput.value,
        role: roleSelect instanceof HTMLSelectElement ? roleSelect.value : null,
        passwordRequired: passwordInput instanceof HTMLInputElement ? passwordInput.required : false,
        passwordPlaceholder: passwordInput instanceof HTMLInputElement ? passwordInput.placeholder : '',
    };

    const synchronizeEmployeeIdentity = () => {
        const selectedOption = employeeSelect.selectedOptions[0];
        const hasEmployee = employeeSelect.value !== '';

        nameInput.readOnly = hasEmployee;
        emailInput.readOnly = hasEmployee;
        nameInput.required = !hasEmployee;
        emailInput.required = !hasEmployee;

        if (hasEmployee) {
            nameInput.value = selectedOption?.dataset.employeeName ?? '';
            emailInput.value = selectedOption?.dataset.employeeEmail ?? '';

            if (provisionsUser && passwordInput instanceof HTMLInputElement) {
                passwordInput.value = '';
                passwordInput.disabled = true;
                passwordInput.required = false;
                passwordInput.placeholder = 'Password sementara ditetapkan otomatis';
            }

            if (roleSelect instanceof HTMLSelectElement) {
                roleSelect.value = 'Staff';
                roleSelect.disabled = true;
                roleSelect.required = false;
            }

            return;
        }

        nameInput.value = manualIdentity.name;
        emailInput.value = manualIdentity.email;

        if (provisionsUser && passwordInput instanceof HTMLInputElement) {
            passwordInput.disabled = false;
            passwordInput.required = manualIdentity.passwordRequired;
            passwordInput.placeholder = manualIdentity.passwordPlaceholder;
        }

        if (roleSelect instanceof HTMLSelectElement) {
            roleSelect.disabled = false;
            roleSelect.required = true;
            roleSelect.value = manualIdentity.role ?? 'Staff';
        }
    };

    employeeSelect.addEventListener('change', synchronizeEmployeeIdentity);
    synchronizeEmployeeIdentity();
});

document.querySelectorAll('[data-role-permission-form]').forEach((form) => {
    const dialog = form.closest('[data-role-permission-dialog]');
    const permissionItems = [...form.querySelectorAll('[data-role-permission]')];
    const selectAllInputs = [...form.querySelectorAll('[data-role-select-all]')];
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const selectionSnapshot = () => permissionItems
        .filter((item) => !item.disabled && item.checked)
        .map((item) => `${item.dataset.role}:${item.value}`)
        .sort()
        .join('|');
    const initialSelection = selectionSnapshot();

    const restoreInitialSelection = () => {
        const initiallySelected = new Set(initialSelection === '' ? [] : initialSelection.split('|'));

        permissionItems.filter((item) => !item.disabled).forEach((item) => {
            item.checked = initiallySelected.has(`${item.dataset.role}:${item.value}`);
        });

        synchronizeRoleSelection();
    };

    const synchronizeRoleSelection = () => {
        selectAllInputs.forEach((selectAll) => {
            if (selectAll.disabled) {
                return;
            }

            const roleItems = permissionItems.filter((item) => item.dataset.role === selectAll.dataset.role);
            const selectedCount = roleItems.filter((item) => item.checked).length;

            selectAll.checked = roleItems.length > 0 && selectedCount === roleItems.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < roleItems.length;
        });
    };

    const requestClose = async () => {
        if (selectionSnapshot() === initialSelection) {
            dialog.close();
            return;
        }

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Batalkan perubahan hak akses?',
            text: 'Perubahan yang belum disimpan akan hilang.',
            showCancelButton: true,
            confirmButtonText: 'Ya, batalkan',
            cancelButtonText: 'Kembali mengatur',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        });

        if (result.isConfirmed) {
            restoreInitialSelection();
            dialog.close();
        }
    };

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        void requestClose();
    });

    dialog.querySelectorAll('[data-role-permission-close]').forEach((button) => {
        button.addEventListener('click', () => void requestClose());
    });

    form.addEventListener('change', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.matches('[data-role-select-all]')) {
            permissionItems
                .filter((item) => item.dataset.role === target.dataset.role && !item.disabled)
                .forEach((item) => {
                    item.checked = target.checked;
                });
        }

        synchronizeRoleSelection();
    });

    form.addEventListener('submit', () => {
        form.querySelector('[data-submit-label]')?.classList.add('hidden');
        form.querySelector('[data-submit-loading]')?.classList.remove('hidden');
    });

    synchronizeRoleSelection();

    if (dialog.hasAttribute('open')) {
        dialog.close();
        dialog.showModal();
    }
});

Alpine.start();
