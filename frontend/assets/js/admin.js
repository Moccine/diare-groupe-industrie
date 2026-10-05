import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.css';
import '../scss/admin.scss';

function openPageHelp(button) {
    const title = button.getAttribute('data-dgi-help-title') || 'Aide';
    const html = button.getAttribute('data-dgi-help-html') || '';

    Swal.fire({
        title,
        html,
        confirmButtonText: 'J’ai compris',
        confirmButtonColor: '#185424',
        width: 'min(48rem, calc(100vw - 1.5rem))',
        focusConfirm: true,
        returnFocus: true,
        heightAuto: true,
        allowEscapeKey: true,
        customClass: {
            popup: 'dgi-help-popup',
            title: 'dgi-help-title',
            htmlContainer: 'dgi-help-body',
            confirmButton: 'dgi-help-confirm',
        },
    });
}

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element
        ? event.target.closest('[data-dgi-page-help]')
        : null;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    event.preventDefault();
    openPageHelp(button);
});
