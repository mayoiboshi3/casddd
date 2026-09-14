/**
 * CASD Corn Portal - Authentication & UI Logic
 */

/* Toggle between Login and Forgot Password views */
function switchView(view) {
    var loginView  = document.getElementById('loginView');
    var forgotView = document.getElementById('forgotView');
    var globalAlert = document.getElementById('globalAlert');

    if (globalAlert) globalAlert.style.display = 'none';

    if (view === 'forgot') {
        loginView.classList.remove('active');
        forgotView.classList.add('active');

        /* Always reset to step 1 */
        var formWrap = document.getElementById('forgotFormWrap');
        var sentBox  = document.getElementById('sentBox');
        if (formWrap) formWrap.style.display = 'block';
        if (sentBox)  sentBox.style.display  = 'none';

        /* Clear the email field */
        var emailInput = document.getElementById('recoveryEmail');
        if (emailInput) emailInput.value = '';

    } else {
        forgotView.classList.remove('active');
        loginView.classList.add('active');
    }
}

/* Password visibility toggle — Login */
function toggleLoginPassword() {
    var inp = document.getElementById('loginPassword');
    var btn = event.target;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = 'HIDE';
    } else {
        inp.type = 'password';
        btn.textContent = 'SHOW';
    }
}