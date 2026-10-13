import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

function orderChanges(form) {
    const status = form.querySelector('select[name="status"]')?.value;
    const payment = form.querySelector('select[name="payment_status"]')?.value;
    return {
        cancel: status === 'cancelled' && status !== form.dataset.originalStatus,
        refund: payment === 'refunded' && payment !== form.dataset.originalPayment,
    };
}

function syncOrderActions(form) {
    const changes = orderChanges(form);
    if (form.matches('[data-admin-order-form]')) {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.classList.toggle('btn-danger', changes.cancel || changes.refund);
        button.classList.toggle('btn-primary', !changes.cancel && !changes.refund);
    } else {
        for (const [action, danger] of [['set_status', changes.cancel], ['set_payment', changes.refund]]) {
            const button = form.querySelector(`button[value="${action}"]`);
            if (!button) continue;
            button.classList.toggle('btn-outline-danger', danger);
            button.classList.toggle('btn-outline-secondary', !danger);
        }
    }
}

document.querySelectorAll('[data-admin-order-form], [data-admin-order-bulk] [data-bulk-form]').forEach((form) => {
    syncOrderActions(form);
});

document.addEventListener('change', (event) => {
    const form = event.target.closest('form');
    if (form?.matches('[data-admin-order-form]') || form?.closest('[data-admin-order-bulk]')) {
        syncOrderActions(form);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    const changes = orderChanges(form);
    let message;
    if (form.matches('[data-admin-order-form]')) {
        if (changes.cancel && changes.refund) message = 'Cancel this order and mark its payment as refunded?';
        else if (changes.cancel) message = 'Cancel this order?';
        else if (changes.refund) message = 'Mark this payment as refunded?';
    } else if (form.matches('[data-bulk-form]') && form.closest('[data-admin-order-bulk]')) {
        const count = form.closest('[data-bulk-root]').querySelectorAll('[data-bulk-row]:checked').length;
        if (!count) return;
        if (event.submitter?.value === 'set_status' && changes.cancel) message = `Cancel ${count} selected order(s)?`;
        if (event.submitter?.value === 'set_payment' && changes.refund) message = `Mark payments for ${count} selected order(s) as refunded?`;
    }
    if (message && !window.confirm(message)) event.preventDefault();
}, true);
