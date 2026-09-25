/**
 * LIFELINE — Blood Donation Portal
 * Vanilla JavaScript Companion (js/main.js)
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Navigation Toggle (Public Header & Dashboards)
    const mobileToggle = document.querySelector('.mobile-toggle');
    const mobileDrawer = document.querySelector('.mobile-drawer') || document.querySelector('.sidebar');
    const drawerOverlay = document.querySelector('.drawer-overlay');

    if (mobileToggle && mobileDrawer) {
        mobileToggle.addEventListener('click', function () {
            mobileDrawer.classList.toggle('active');
            if (drawerOverlay) {
                drawerOverlay.classList.toggle('active');
            }
            const isExpanded = mobileDrawer.classList.contains('active');
            mobileToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }

    if (drawerOverlay && mobileDrawer) {
        drawerOverlay.addEventListener('click', function () {
            mobileDrawer.classList.remove('active');
            drawerOverlay.classList.remove('active');
            if (mobileToggle) {
                mobileToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // 2. Interactive Blood Compatibility Selector (Landing Page)
    const bloodTypeButtons = document.querySelectorAll('.compat-btn');
    const giveList = document.getElementById('compat-give-list');
    const receiveList = document.getElementById('compat-receive-list');
    const selectedTypeDisplay = document.getElementById('compat-selected-type');

    // Compatibility matrix
    const compatibilityData = {
        'A+': {
            give: ['A+', 'AB+'],
            receive: ['A+', 'A-', 'O+', 'O-'],
            desc: 'A+ can give red blood cells to A+ and AB+, and receive from A+, A-, O+, and O-.'
        },
        'A-': {
            give: ['A+', 'A-', 'AB+', 'AB-'],
            receive: ['A-', 'O-'],
            desc: 'A- can give to all A and AB types, and receive from A- and O-.'
        },
        'B+': {
            give: ['B+', 'AB+'],
            receive: ['B+', 'B-', 'O+', 'O-'],
            desc: 'B+ can give to B+ and AB+, and receive from B+, B-, O+, and O-.'
        },
        'B-': {
            give: ['B+', 'B-', 'AB+', 'AB-'],
            receive: ['B-', 'O-'],
            desc: 'B- can give to all B and AB types, and receive from B- and O-.'
        },
        'AB+': {
            give: ['AB+'],
            receive: ['Universal Recipient (All blood types)'],
            desc: 'AB+ is the universal recipient for red blood cells, receiving from any type.'
        },
        'AB-': {
            give: ['AB+', 'AB-'],
            receive: ['AB-', 'A-', 'B-', 'O-'],
            desc: 'AB- can give to AB+ and AB-, and receive from all negative types.'
        },
        'O+': {
            give: ['O+', 'A+', 'B+', 'AB+'],
            receive: ['O+', 'O-'],
            desc: 'O+ can give to all positive blood types and receive from O+ and O-.'
        },
        'O-': {
            give: ['Universal Donor (All blood types)'],
            receive: ['O-'],
            desc: 'O- is the universal red cell donor, vital for emergency transfusions.'
        }
    };

    if (bloodTypeButtons.length > 0 && giveList && receiveList) {
        bloodTypeButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                bloodTypeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const type = this.getAttribute('data-type');
                const data = compatibilityData[type];

                if (data) {
                    if (selectedTypeDisplay) {
                        selectedTypeDisplay.textContent = type;
                    }

                    // Render Give tags
                    giveList.innerHTML = '';
                    data.give.forEach(item => {
                        const span = document.createElement('span');
                        span.className = 'compat-chip ' + (item.includes('Universal') ? 'chip-universal' : '');
                        span.textContent = item;
                        giveList.appendChild(span);
                    });

                    // Render Receive tags
                    receiveList.innerHTML = '';
                    data.receive.forEach(item => {
                        const span = document.createElement('span');
                        span.className = 'compat-chip ' + (item.includes('Universal') ? 'chip-universal' : '');
                        span.textContent = item;
                        receiveList.appendChild(span);
                    });
                }
            });
        });
    }

    // 3. Dismissible Alerts
    const alertDismissButtons = document.querySelectorAll('.alert-dismiss');
    alertDismissButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const parentAlert = this.closest('.message, .alert');
            if (parentAlert) {
                parentAlert.style.opacity = '0';
                parentAlert.style.transform = 'translateY(-6px)';
                setTimeout(() => parentAlert.remove(), 180);
            }
        });
    });

    // 4. Registration Password Confirm Matching Feedback
    const regForm = document.querySelector('form.register-form');
    const pwdInput = document.getElementById('password');
    const confirmPwdInput = document.getElementById('confirm_password');
    const pwdMismatchMsg = document.getElementById('password-mismatch-msg');

    if (regForm && pwdInput && confirmPwdInput) {
        function checkPasswordMatch() {
            if (confirmPwdInput.value.length > 0) {
                if (pwdInput.value !== confirmPwdInput.value) {
                    confirmPwdInput.style.borderColor = '#A9344B';
                    if (pwdMismatchMsg) pwdMismatchMsg.style.display = 'block';
                } else {
                    confirmPwdInput.style.borderColor = '#4F8065';
                    if (pwdMismatchMsg) pwdMismatchMsg.style.display = 'none';
                }
            } else {
                confirmPwdInput.style.borderColor = '';
                if (pwdMismatchMsg) pwdMismatchMsg.style.display = 'none';
            }
        }
        confirmPwdInput.addEventListener('input', checkPasswordMatch);
        pwdInput.addEventListener('input', checkPasswordMatch);
    }
});
