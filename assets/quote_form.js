/*
 * Formulaire de devis : ajout/suppression de lignes et totaux en direct.
 * Les totaux affichés ici sont purement indicatifs : le serveur recalcule tout
 * (App\Service\QuoteCalculator) et reste la seule source de vérité.
 */
const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

function toCents(value) {
    const n = Number.parseFloat(String(value).replace(',', '.'));
    return Number.isFinite(n) ? Math.round(n * 100) : 0;
}

function lineTotal(row) {
    const qty = toCents(row.querySelector('[data-role=quantity]')?.value); // centièmes
    const price = toCents(row.querySelector('[data-role=unit-price]')?.value); // centimes
    return Math.floor((qty * price + 50) / 100);
}

function refresh(form) {
    const bases = new Map();
    form.querySelectorAll('[data-line-row]').forEach((row) => {
        const total = lineTotal(row);
        const rate = Number.parseInt(row.querySelector('[data-role=vat-rate]')?.value ?? '0', 10);
        row.querySelector('[data-line-total]').textContent = euro.format(total / 100);
        bases.set(rate, (bases.get(rate) ?? 0) + total);
    });

    let ht = 0;
    let vat = 0;
    bases.forEach((base, rate) => {
        ht += base;
        vat += Math.floor((base * rate + 5000) / 10000);
    });

    form.querySelector('[data-total-ht]').textContent = euro.format(ht / 100);
    form.querySelector('[data-total-vat]').textContent = euro.format(vat / 100);
    form.querySelector('[data-total-ttc]').innerHTML = `<strong>${euro.format((ht + vat) / 100)}</strong>`;
}

function init(form) {
    const container = form.querySelector('[data-lines]');
    let index = Number.parseInt(container.dataset.index, 10) || 0;

    const addLine = () => {
        container.insertAdjacentHTML('beforeend', container.dataset.prototype.replaceAll('__name__', String(index++)));
        refresh(form);
    };

    form.querySelector('[data-add-line]').addEventListener('click', addLine);
    container.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');
        if (button) {
            button.closest('[data-line-row]').remove();
            refresh(form);
        }
    });
    form.addEventListener('input', () => refresh(form));

    if (container.children.length === 0) {
        addLine();
    }
    refresh(form);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-quote-form]').forEach(init);
});
