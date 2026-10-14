/**
 * KopiKita Coffee & Roastery ERP
 * Core JavaScript Functions
 */

function formatRupiahJS(number) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
}

function showToast(message, type = 'success') {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: type,
            title: message
        });
    } else {
        alert(message);
    }
}

function printThermalReceipt() {
    window.print();
}

// Global table search filter
document.addEventListener('DOMContentLoaded', function () {
    const searchInputs = document.querySelectorAll('.table-search-input');
    searchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-target');
        const table = document.getElementById(targetTableId);
        if (!table) return;

        input.addEventListener('keyup', function () {
            const query = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });
});
