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
// Order form: add/remove item rows, fill prices from the price list
// and calculate totals
// ---------------------------------------------------------------------
function initOrderItems() {
    var table = document.getElementById('items-table');
    if (!table) { return; }
    var body = table.querySelector('tbody');
    var template = document.getElementById('item-row-template');
    var symbol = table.dataset.currency || '$';
    var prices = JSON.parse(table.dataset.prices || '{}');
    var speed = document.getElementById('service_speed');
    var customer = document.getElementById('customer_id');

    function money(n) {
        var sign = n < 0 ? '-' : '';
        return sign + symbol + (Math.round(Math.abs(n) * 100) / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function round2(n) { return Math.round(n * 100) / 100; }

    // Fill the price from the price list (unless the user typed a price)
    function fillPrice(row) {
        var name = row.querySelector('input[name="item_name[]"]').value.trim().toLowerCase();
        var service = row.querySelector('select[name="service_type[]"]').value.toLowerCase();
        var priceInput = row.querySelector('.item-price');
        var key = name + '|' + service;
        var pkg = speed ? speed.value : 'Normal';   // Normal / Silver / Gold have their own prices
        if (Object.prototype.hasOwnProperty.call(prices, key) && (priceInput.value === '' || priceInput.dataset.auto === '1')) {
            var p = prices[key][pkg] !== undefined ? prices[key][pkg] : prices[key].Normal;
            priceInput.value = Number(p).toFixed(2);
            priceInput.dataset.auto = '1';
        }
    }

    // Package extra % (old orders only) and customer level discount % (same rules as the server)
    function percents() {
        var speedPct = 0, discountPct = 0;
        var editing = table.dataset.edit === '1';
        if (speed) {
            speedPct = (editing && speed.value === table.dataset.originalSpeed)
                ? parseFloat(table.dataset.originalSpeedPercent) || 0
                : parseFloat(speed.selectedOptions[0].dataset.percent) || 0;
        }
        var opt = customer ? customer.selectedOptions[0] : null;
        if (opt && opt.value) {
            discountPct = (editing && opt.value === table.dataset.originalCustomer)
                ? parseFloat(table.dataset.originalDiscount) || 0
                : parseFloat(opt.dataset.discount) || 0;
        }
        return { speed: speedPct, discount: discountPct };
    }

    // Item total = Quantity x Price; Order total = Subtotal + charge - discount
    function recalc() {
        var subtotal = 0;
        body.querySelectorAll('tr').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            var price = parseFloat(row.querySelector('.item-price').value) || 0;
            var total = qty * price;
            row.querySelector('.item-total').textContent = money(total);
            subtotal += total;
        });
        subtotal = round2(subtotal);
        var p = percents();
        var speedCharge = round2(subtotal * p.speed / 100);
        var discount = round2((subtotal + speedCharge) * p.discount / 100);
        var grand = round2(subtotal + speedCharge - discount);

        document.getElementById('order-subtotal').textContent = money(subtotal);
        document.getElementById('order-speed').textContent = money(speedCharge);
        document.getElementById('order-discount').textContent = money(-discount);
        document.getElementById('speed-percent').textContent = p.speed ? '(' + p.speed + '%)' : '';
        document.getElementById('discount-percent').textContent = p.discount ? '(' + p.discount + '%)' : '';
        document.getElementById('row-speed').style.display = p.speed ? '' : 'none';
        document.getElementById('row-discount').style.display = p.discount ? '' : 'none';
        document.getElementById('order-total').textContent = money(grand);

        var paidInput = document.getElementById('amount_paid');
        if (paidInput) {
            var paid = parseFloat(paidInput.value) || 0;
            document.getElementById('order-balance').textContent = money(grand - paid);
            paidInput.max = grand.toFixed(2);
        }
    }

    function addRow() {
        body.appendChild(template.content.cloneNode(true));
        recalc();
    }

    document.getElementById('add-item').addEventListener('click', addRow);

    body.addEventListener('input', function (event) {
        if (event.target.classList.contains('item-price')) {
            event.target.dataset.auto = '0';      // the user typed a price: keep it
        } else if (event.target.name === 'item_name[]') {
            fillPrice(event.target.closest('tr'));
        }
        recalc();
    });
    body.addEventListener('change', function (event) {
        if (event.target.name === 'service_type[]' || event.target.name === 'item_name[]') {
            fillPrice(event.target.closest('tr'));
            recalc();
        }
    });
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
    if (speed) {
        // New package: use that package's prices for the rows that were filled automatically
        speed.addEventListener('change', function () {
            body.querySelectorAll('tr').forEach(fillPrice);
            recalc();
        });
    }
    if (customer) { customer.addEventListener('change', recalc); }

    if (!body.querySelector('tr')) { addRow(); }
    recalc();
}
