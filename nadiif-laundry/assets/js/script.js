// =====================================================================
// NADIIF LAUNDRY - small JavaScript helpers
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {

    // Stop double clicks: disable the button after a form is submitted
    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) { return; }
            if (form.dataset.submitted === 'yes') { event.preventDefault(); return; }
            form.dataset.submitted = 'yes';
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                btn.dataset.originalText = btn.innerHTML;
                btn.innerHTML = 'Please wait...';
            });
        });
    });

    // Ask "Are you sure?" before delete buttons
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        var handler = function (event) {
            if (!confirm(el.dataset.confirm)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        };
        if (el.tagName === 'FORM') {
            el.addEventListener('submit', handler, true);
        } else {
            el.addEventListener('click', handler);
        }
    });

    // Filter a long dropdown by typing (used for choosing a customer)
    document.querySelectorAll('[data-filter-select]').forEach(function (input) {
        var select = document.getElementById(input.dataset.filterSelect);
        if (!select) { return; }
        input.addEventListener('input', function () {
            var text = input.value.toLowerCase();
            var firstMatch = null;
            Array.prototype.forEach.call(select.options, function (opt) {
                if (!opt.value) { return; }
                var show = opt.text.toLowerCase().indexOf(text) !== -1;
                opt.hidden = !show;
                if (show && !firstMatch) { firstMatch = opt; }
            });
            if (firstMatch && select.selectedOptions.length && select.selectedOptions[0].hidden) {
                select.value = firstMatch.value;
                select.dispatchEvent(new Event('change'));
            }
        });
    });

    initOrderItems();
});

// ---------------------------------------------------------------------
// Order form: add/remove item rows and calculate totals
// ---------------------------------------------------------------------
function initOrderItems() {
    var table = document.getElementById('items-table');
    if (!table) { return; }
    var body = table.querySelector('tbody');
    var template = document.getElementById('item-row-template');
    var symbol = table.dataset.currency || '$';

    function money(n) {
        return symbol + (Math.round(n * 100) / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Calculate item totals (Quantity x Price) and the order total
    function recalc() {
        var grand = 0;
        body.querySelectorAll('tr').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            var price = parseFloat(row.querySelector('.item-price').value) || 0;
            var total = qty * price;
            row.querySelector('.item-total').textContent = money(total);
            grand += total;
        });
        document.getElementById('order-total').textContent = money(grand);
        var paidInput = document.getElementById('amount_paid');
        if (paidInput) {
            var paid = parseFloat(paidInput.value) || 0;
            document.getElementById('order-balance').textContent = money(grand - paid);
            paidInput.max = (Math.round(grand * 100) / 100).toFixed(2);
        }
    }

    function addRow() {
        body.appendChild(template.content.cloneNode(true));
        recalc();
    }

    document.getElementById('add-item').addEventListener('click', addRow);

    body.addEventListener('input', recalc);
    body.addEventListener('click', function (event) {
        var btn = event.target.closest('.remove-item');
        if (!btn) { return; }
        if (body.querySelectorAll('tr').length > 1) {
            btn.closest('tr').remove();
        } else {
            btn.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('item-qty') ? 1 : ''; });
        }
        recalc();
    });
    var paidInput = document.getElementById('amount_paid');
    if (paidInput) { paidInput.addEventListener('input', recalc); }

    if (!body.querySelector('tr')) { addRow(); }
    recalc();
}
