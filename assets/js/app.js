/**
 * KopiKita Coffee & Roastery ERP
 * Interactive Core JavaScript Engine
 */

// Format currency to Indonesian Rupiah
function formatRupiahJS(number) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
}

// Global SweetAlert Toast helper
function showToast(message, type = 'success') {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        Toast.fire({
            icon: type,
            title: message
        });
    } else {
        alert(message);
    }
}

// Thermal receipt printing
function printThermalReceipt() {
    window.print();
}

// Web Audio API Sound Generator (No external dependencies)
const AppAudio = {
    ctx: null,
    init() {
        if (!this.ctx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) this.ctx = new AudioContext();
        }
    },
    playTone(freq, type, duration, gainValue = 0.08) {
        try {
            this.init();
            if (!this.ctx) return;
            if (this.ctx.state === 'suspended') this.ctx.resume();
            
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
            gain.gain.setValueAtTime(gainValue, this.ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.start();
            osc.stop(this.ctx.currentTime + duration);
        } catch (e) {
            // Ignore audio context block
        }
    },
    click() {
        this.playTone(600, 'sine', 0.06, 0.05);
    },
    success() {
        this.playTone(523.25, 'triangle', 0.12, 0.08); // C5
        setTimeout(() => this.playTone(659.25, 'triangle', 0.16, 0.08), 80); // E5
        setTimeout(() => this.playTone(783.99, 'triangle', 0.25, 0.1), 160); // G5
    },
    pop() {
        this.playTone(880, 'sine', 0.08, 0.06);
    }
};

// Theme Management (Light / Dark mode)
function initTheme() {
    const savedTheme = localStorage.getItem('kopikita_theme') || 'light';
    setTheme(savedTheme, false);
}

function setTheme(theme, playSfx = true) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('kopikita_theme', theme);

    const icon = document.getElementById('themeIcon');
    if (icon) {
        if (theme === 'dark') {
            icon.className = 'bi bi-sun-fill text-warning';
        } else {
            icon.className = 'bi bi-moon-fill';
        }
    }

    if (playSfx) {
        AppAudio.click();
    }
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(nextTheme, true);
    showToast(nextTheme === 'dark' ? 'Mode Gelap diaktifkan' : 'Mode Terang diaktifkan', 'info');
}

// Spotlight Command Palette (Cmd+K / Ctrl+K)
function openCommandPalette() {
    const modalEl = document.getElementById('commandPaletteModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        setTimeout(() => {
            const input = document.getElementById('cmdPaletteInput');
            if (input) {
                input.focus();
                input.select();
            }
        }, 200);
    }
}

// Document Ready Initialization
document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Theme
    initTheme();

    // 2. Global Keyboard Shortcut for Command Palette (Cmd+K / Ctrl+K)
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openCommandPalette();
        }
    });

    // 3. Command Palette live filter
    const cmdInput = document.getElementById('cmdPaletteInput');
    const cmdResults = document.getElementById('cmdPaletteResults');
    if (cmdInput && cmdResults) {
        cmdInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const items = cmdResults.querySelectorAll('.list-group-item');
            let hasVisible = false;

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = '';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });

            let noResultMsg = document.getElementById('cmdNoResultMsg');
            if (!hasVisible) {
                if (!noResultMsg) {
                    noResultMsg = document.createElement('div');
                    noResultMsg.id = 'cmdNoResultMsg';
                    noResultMsg.className = 'text-center py-4 text-muted small';
                    noResultMsg.innerHTML = '<i class="bi bi-search me-1"></i> Tidak ada modul atau aksi yang cocok';
                    cmdResults.appendChild(noResultMsg);
                }
                noResultMsg.style.display = 'block';
            } else if (noResultMsg) {
                noResultMsg.style.display = 'none';
            }
        });
    }

    // 4. Interactive Profile & Manager Name Update via AJAX
    const formUpdateProfile = document.getElementById('formUpdateProfile');
    if (formUpdateProfile) {
        formUpdateProfile.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
            }

            const formData = new FormData(this);
            formData.append('ajax', '1');

            fetch('update_profile.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.user) {
                    // Update header display instantly
                    const nameEl = document.getElementById('headerUserName');
                    const roleEl = document.getElementById('headerUserRole');
                    const initialsEl = document.getElementById('headerUserInitials');

                    if (nameEl) nameEl.textContent = data.user.name;
                    if (roleEl) roleEl.textContent = data.user.role;
                    if (initialsEl) initialsEl.textContent = data.user.initials;

                    // Close modal
                    const modalEl = document.getElementById('userProfileModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }

                    AppAudio.success();
                    showToast(data.message || 'Nama Manager & Profil berhasil diperbarui!', 'success');
                } else {
                    showToast(data.message || 'Gagal memperbarui profil', 'error');
                }
            })
            .catch(err => {
                console.error('Profile update error:', err);
                // Fallback to normal submit if ajax network fails
                formUpdateProfile.submit();
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan Nama & Profil';
                }
            });
        });
    }

    // 5. Global Table Search Filter
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

    // 6. Interactive click sound on primary action buttons
    document.querySelectorAll('.btn-coffee, .btn-accent, .btn-warning').forEach(btn => {
        btn.addEventListener('click', function () {
            AppAudio.click();
        });
    });
});
