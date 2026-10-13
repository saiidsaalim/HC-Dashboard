

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

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

Alpine.start();
