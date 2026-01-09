// ==================== MODAL FUNCTIONS ====================
function openTambahWarga() {
    document.getElementById('modalTambah').classList.add('active');
    document.getElementById('formTambahWarga').reset();
    // Reset pesan error jika ada
    clearFormErrors('formTambahWarga');
}

function closeTambahWarga() {
    document.getElementById('modalTambah').classList.remove('active');
}

async function openEditWarga(id) {
    try {
        const response = await fetch(`../backend/warga/get_warga.php?id_user=${id}`);
        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        // Fill form with data
        document.getElementById('edit_id_user').value = data.id_user;
        document.getElementById('edit_id_warga').value = data.id_warga || '';
        document.getElementById('edit_nama_lengkap').value = data.nama_lengkap || '';
        document.getElementById('edit_username').value = data.username || '';
        document.getElementById('edit_email').value = data.email || data.username || '';
        document.getElementById('edit_nik').value = data.nik || '';
        document.getElementById('edit_rt').value = data.rt_number || '';
        document.getElementById('edit_role').value = data.role || 'warga';

        // Reset password field
        document.getElementById('edit_password').value = '';

        document.getElementById('modalEdit').classList.add('active');

    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'Gagal mengambil data warga. Periksa koneksi internet Anda.');
    }
}

function closeEditWarga() {
    document.getElementById('modalEdit').classList.remove('active');
}

// ==================== FILTER FUNCTIONS ====================
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterRole').value = '';

    filterTable();

    // Show notification
    showAlert('info', 'Filter telah direset');
}

// Real-time filtering
document.getElementById('searchInput').addEventListener('input', debounce(filterTable, 300));
document.getElementById('filterRole').addEventListener('change', filterTable);

// Add event listener for search button
document.getElementById('btnSearch')?.addEventListener('click', filterTable);

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    const selectedRT = document.getElementById('filterRT').value;
    const selectedRole = document.getElementById('filterRole').value;

    const rows = document.querySelectorAll('.data-table tbody tr');
    let visibleCount = 0;
    const totalRows = rows.length;

    rows.forEach(row => {
        if (row.classList.contains('empty-row')) return;

        const name = row.cells[0]?.querySelector('.warga-nama')?.textContent?.toLowerCase() || '';
        const email = row.cells[0]?.querySelector('.warga-email')?.textContent?.toLowerCase() || '';
        const nik = row.cells[1]?.textContent?.toLowerCase() || '';
        const rt = row.cells[2]?.textContent || '';
        const role = row.cells[3]?.textContent || '';

        // Search in multiple fields
        const searchMatch = !searchTerm ||
                           name.includes(searchTerm) ||
                           email.includes(searchTerm) ||
                           nik.includes(searchTerm);

        const rtMatch = !selectedRT || rt === selectedRT;
        const roleMatch = !selectedRole || role === selectedRole;

        if (searchMatch && rtMatch && roleMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Update table title with filter count
    updateTableCount(visibleCount, totalRows);
}

function updateTableCount(visible, total) {
    const tableTitle = document.querySelector('.table-title');
    if (tableTitle) {
        if (visible === total) {
            tableTitle.textContent = `Daftar Warga (${total} data)`;
        } else {
            tableTitle.textContent = `Daftar Warga (${visible} dari ${total} data)`;
        }
    }
}

// ==================== FORM VALIDATION ====================
document.getElementById('formTambahWarga')?.addEventListener('submit', function(e) {
    if (!validateTambahForm()) {
        e.preventDefault();
    }
});

document.getElementById('formEditWarga')?.addEventListener('submit', function(e) {
    if (!validateEditForm()) {
        e.preventDefault();
    }
});

function validateTambahForm() {
    const form = document.getElementById('formTambahWarga');
    let isValid = true;

    // Clear previous errors
    clearFormErrors('formTambahWarga');

    // Validate Nama Lengkap
    const namaLengkap = form.querySelector('input[name="nama_lengkap"]').value.trim();
    if (!namaLengkap) {
        showFieldError('nama_lengkap', 'Nama lengkap harus diisi');
        isValid = false;
    }

    // Validate Email
    const email = form.querySelector('input[name="email"]').value;
    if (!validateEmail(email)) {
        showFieldError('email', 'Format email tidak valid');
        isValid = false;
    }

    // Validate NIK
    const nik = form.querySelector('input[name="nik"]').value;
    if (nik.length !== 16 || !/^\d{16}$/.test(nik)) {
        showFieldError('nik', 'NIK harus 16 digit angka');
        isValid = false;
    }

    // Validate Role
    const role = form.querySelector('select[name="role"]').value;
    if (!role) {
        showFieldError('role', 'Pilih Role terlebih dahulu');
        isValid = false;
    }

    if (!isValid) {
        showAlert('error', 'Periksa kembali data yang dimasukkan');
    }

    return isValid;
}

function validateEditForm() {
    const form = document.getElementById('formEditWarga');
    let isValid = true;

    // Clear previous errors
    clearFormErrors('formEditWarga');

    // Validate NIK
    const nik = form.querySelector('input[name="nik"]').value;
    if (nik.length !== 16 || !/^\d{16}$/.test(nik)) {
        showFieldError('edit_nik', 'NIK harus 16 digit angka');
        isValid = false;
    }

    // Validate Username
    const username = form.querySelector('input[name="username"]').value;
    if (!username || !/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
        showFieldError('edit_username', 'Username 3-50 karakter (huruf, angka, underscore)');
        isValid = false;
    }

    // Validate Email
    const email = form.querySelector('input[name="email"]').value;
    if (!validateEmail(email)) {
        showFieldError('edit_email', 'Format email tidak valid');
        isValid = false;
    }

    // Validate Password (optional)
    const password = form.querySelector('input[name="password"]').value;
    if (password && password.length < 6) {
        showFieldError('edit_password', 'Password minimal 6 karakter (biarkan kosong jika tidak ingin mengubah)');
        isValid = false;
    }

    if (!isValid) {
        showAlert('error', 'Periksa kembali data yang dimasukkan');
    }

    return isValid;
}

// ==================== UTILITY FUNCTIONS ====================
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showFieldError(fieldName, message) {
    const field = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(fieldName);
    if (field) {
        // Add error class to field
        field.classList.add('is-invalid');

        // Create or update error message
        let errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            field.parentNode.appendChild(errorElement);
        }
        errorElement.textContent = message;
    }
}

function clearFormErrors(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    // Remove error classes
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });

    // Remove error messages
    form.querySelectorAll('.invalid-feedback').forEach(el => {
        el.remove();
    });
}

function showAlert(type, message) {
    // Remove existing alerts
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) existingAlert.remove();

    // Create alert element
    const alert = document.createElement('div');
    alert.className = `custom-alert alert-${type}`;
    alert.innerHTML = `
        <div class="alert-content">
            <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'error' ? 'bi-exclamation-circle' : 'bi-info-circle'}"></i>
            <span>${message}</span>
            <button class="alert-close">&times;</button>
        </div>
    `;

    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .custom-alert {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 2000;
            min-width: 300px;
            max-width: 400px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .alert-success { background: var(--success-color); }
        .alert-error { background: var(--danger-color); }
        .alert-info { background: var(--info-color); }
        .alert-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        .alert-close:hover {
            background: rgba(255,255,255,0.2);
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;

    document.head.appendChild(style);
    document.body.appendChild(alert);

    // Add close functionality
    alert.querySelector('.alert-close').addEventListener('click', () => {
        alert.style.animation = 'slideOut 0.3s ease-out';
        alert.style.transform = 'translateX(100%)';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    });

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'slideOut 0.3s ease-out';
            alert.style.transform = 'translateX(100%)';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

// ==================== EVENT LISTENERS ====================
// Close modals with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTambahWarga();
        closeEditWarga();
    }
});

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Prevent form submission on Enter in search
document.getElementById('searchInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        filterTable();
    }
});

// ==================== INITIALIZATION ====================
// Initialize filter on page load
document.addEventListener('DOMContentLoaded', function() {
    filterTable();

    // Add CSS for validation
    const validationStyles = document.createElement('style');
    validationStyles.textContent = `
        .is-invalid {
            border-color: var(--danger-color) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .is-invalid:focus {
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        }
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: var(--danger-color);
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(validationStyles);
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showAlert('error', 'Terjadi kesalahan pada aplikasi');
});
