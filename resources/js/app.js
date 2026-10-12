

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

async function submitAfterConfirmation(event) {
    event.preventDefault();
    const form = this;

    const result = await Swal.fire({
        title: form.dataset.confirmTitle,
        text: form.dataset.confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButtonText ?? 'Ya, hapus',
        cancelButtonText: form.dataset.cancelButtonText ?? 'Batal',
        confirmButtonColor: form.dataset.confirmButtonColor ?? '#dc2626',
        cancelButtonColor: form.dataset.cancelButtonColor ?? '#64748b',
        reverseButtons: true,
        focusCancel: true,
    });

    if (result.isConfirmed) {
        form.submit();
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-delete], [data-confirm-submit]')) {
        return;
    }

    void submitAfterConfirmation.call(form, event);
});

Alpine.start();
