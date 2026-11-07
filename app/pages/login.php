<?php
session_start();
require_once(__DIR__ . "/../../_shared/auth.php");

if (isAnyUserLoggedIn()) {
    $homeUrl = $_SESSION['nha_home_url'] ?? '/MainDashboard';
    header('Location: ' . $homeUrl);
    exit;
}

// Success flash after a completed password change (from /changepassword.php → /changepassword → back).
$pwdChanged = isset($_GET['pwd']) && $_GET['pwd'] === 'changed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHA FIMS — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@100;300;400&family=Roboto:wght@400;500&family=Geist:wght@300;400&family=Syne:wght@400&family=IBM+Plex+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Geist', 'Segoe UI', system-ui, -apple-system, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: #00091f;
        }

        /* ══════════════════════════════════════
           PAGE BACKGROUND — shared image + overlay
        ══════════════════════════════════════ */
        .page-bg {
            position: fixed;
            inset: 0;
            background-image: url('/_shared/images/tree-greenyard.jpg');
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        .page-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 23, 86, 0.85);
            backdrop-filter: blur(7.5px);
            -webkit-backdrop-filter: blur(7.5px);
            z-index: 1;
        }

        .main-container {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: stretch;
            height: 100vh;
            width: 100%;
            padding: 100px 50px;
            gap: 0;
        }

        .content-box {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-height: 800px;
            filter: drop-shadow(0 0 12.5px rgba(0, 14, 62, 0.25));
        }

        /* ══════════════════════════════════════
           LEFT PANEL — branding
        ══════════════════════════════════════ */
        .logo-section {
            --logo-width: 60%; /* change this to control the panel width, e.g. 480px or 60% */
            flex: 0 0 auto;
            width: var(--logo-width);
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 50px;
        }

        /* top: logo + headings */
        .left-header {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo-wrap {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 50px;
            margin-bottom: 0;
        }

        .logo-wrap-inner {
            width: 150px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .left-logo-svg {
            width: 150px;
            height: 150px;
        }

        .left-headings {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
            color: #f5faf5;
        }

        .heading-main {
            font-family: 'Barlow Semi Condensed', sans-serif;
            font-size: 35px;
            font-weight: 400;
            font-style: normal;
            line-height: 32px;
            letter-spacing: 0;
        }

        .heading-sub {
            font-family: 'Barlow Semi Condensed', sans-serif;
            font-size: 25px;
            font-weight: 400;
            font-style: normal;
            line-height: 24px;
            letter-spacing: 0;
        }

        /* bottom: secure login badge */
        .left-footer {
            width: 100%;
        }

        .nha-badge {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .badge-shield {
            width: 48px;
            height: 48px;
            border: 1px solid #fcd20f;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            padding: 1px;
        }

        .badge-shield svg {
            width: 24px;
            height: 24px;
            fill: #fcd20f;
        }

        .badge-label {
            color: #fcd20f;
            font-family: 'Roboto', sans-serif;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 2.4px;
            text-transform: uppercase;
        }

        /* ══════════════════════════════════════
           RIGHT PANEL — login form card
        ══════════════════════════════════════ */
        .login-container {
            --login-width: 30%; /* change this to control the panel width, e.g. 480px or 40% */
            display: flex;
            padding: 50px;
            flex-direction: column;
            align-items: flex-start;
            gap: 25px;
            flex: 0 0 auto;
            width: var(--login-width);
            align-self: stretch;
            border-radius: 10px;
            background: rgba(0, 7, 43, 0.90);
            box-shadow: 0 4px 25px 0 rgba(0, 14, 62, 0.25);
        }

        .login-form {
            display: flex;
            padding: 20px 0;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            flex: 1 0 0;
            align-self: stretch;
        }

        .form-margin {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            align-self: stretch;
            width: 100%;
        }

        .form-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            align-self: stretch;
        }

        .form-margin form {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
            align-self: stretch;
            width: 100%;
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #11a752;
            font-size: 14px;
            font-weight: 400;
            font-family: 'Roboto', sans-serif;
            line-height: 20px;
            text-transform: uppercase;
        }

        .form-subtitle {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            align-self: stretch;
            color: #f5faf5;
            text-align: center;
            font-family: 'Barlow Semi Condensed', sans-serif;
            font-size: 17px;
            font-weight: 400;
            line-height: 32px;
        }

        .flash-error {
            background: rgba(220,38,38,0.12);
            border: 1px solid rgba(220,38,38,0.3);
            color: #fca5a5;
            padding: 10px 14px;
            font-size: 12px;
            border-radius: 3px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field-group {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            align-self: stretch;
            gap: 10px;
            border-bottom: 2px solid #7c8ca3;
            padding: 15px 0;
            width: 100%;
            transition: border-color 0.2s;
        }

        .field-group:focus-within {
            border-bottom-color: rgba(252,210,15,0.65);
        }

        .field-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            flex: 1 0 0;
            min-width: 0;
            padding: 5px 0;
        }

        .field-label {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: #f5faf5;
            font-family: 'Geist', sans-serif;
            font-size: 17px;
            font-weight: 400;
            line-height: 21px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            pointer-events: none;
            transition: top 0.18s ease, font-size 0.18s ease, transform 0.18s ease, color 0.18s ease;
        }

        .field-input:focus + .field-label,
        .field-input:not(:placeholder-shown) + .field-label,
        .field-input:-webkit-autofill + .field-label {
            top: -14px;
            transform: translateY(0);
            font-size: 11px;
            color: rgba(245,250,245,0.65);
        }

        .field-input {
            align-self: stretch;
            width: 100%;
            background: transparent;
            border: none;
            padding: 0 2rem 0 0;
            color: #fff;
            font-size: 18px;
            font-family: "IBM Plex Mono", monospace;
            font-weight: 400;
            outline: none;
        }

        .field-input:-webkit-autofill,
        .field-input:-webkit-autofill:hover,
        .field-input:-webkit-autofill:focus,
        .field-input:-webkit-autofill:active {
            -webkit-text-fill-color: #fff;
            -webkit-box-shadow: 0 0 0 1000px rgba(0, 7, 43, 0.90) inset;
            box-shadow: 0 0 0 1000px rgba(0, 7, 43, 0.90) inset;
            caret-color: #fff;
            transition: background-color 5000s ease-in-out 0s;
        }

        .toggle-password {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.28);
            cursor: pointer;
            padding: 4px;
            transition: color 0.15s;
        }

        .toggle-password:hover { color: rgba(255,255,255,0.65); }

        .retain-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .retain-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #fcd20f;
            cursor: pointer;
            flex-shrink: 0;
            border: 1px solid #7c8ca3;
        }

        .retain-row label {
            color: #f5faf5;
            font-family: 'Geist', sans-serif;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
        }

        .login-btn {
            width: 100%;
            padding: 16px 24px;
            background: #fcd20f;
            color: #020617;
            border: none;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 2.4px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.18s;
        }

        .login-btn:hover:not(:disabled) { background: #e8be00; }

        .login-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .login-footer {
            padding-left: 18px;
            border-left: 2px solid #7c8ca3;
            color: rgba(255,255,255,0.6);
            font-family: 'Geist', sans-serif;
            font-size: 10px;
            line-height: 12px;
        }

        /* ══════════════════════════════════════
           FORGOT PASSWORD — link + reset panel
        ══════════════════════════════════════ */
        .forgot-row {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-top: -4px;
        }

        .forgot-link {
            background: none;
            border: none;
            padding: 0;
            color: rgba(252,210,15,0.85);
            font-family: 'Geist', sans-serif;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
            transition: color 0.18s;
        }

        .forgot-link:hover { color: #fcd20f; text-decoration: underline; }

        .flash-success {
            background: rgba(17,167,82,0.12);
            border: 1px solid rgba(17,167,82,0.35);
            color: #86efac;
            padding: 10px 14px;
            font-size: 12px;
            border-radius: 3px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }

        .back-link {
            background: none;
            border: none;
            padding: 0;
            color: rgba(245,250,245,0.65);
            font-family: 'Geist', sans-serif;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.18s;
        }

        .back-link:hover { color: #f5faf5; }

        .field-input.invalid ~ .field-label { color: #fca5a5; }
        .field-group.invalid { border-bottom-color: rgba(220,38,38,0.65); }

        .field-error {
            color: #fca5a5;
            font-family: 'Geist', sans-serif;
            font-size: 11px;
            margin-top: 4px;
            display: none;
        }
        .field-error.show { display: block; }

        /* ══════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════ */
        @media (max-width: 900px) {
            body { overflow-y: auto; height: auto; }
            .page-bg, .page-overlay { position: absolute; }
            .main-container { height: auto; padding: 2.5rem 1.5rem; }
            .content-box { flex-direction: column; min-height: 0; }
            .logo-section { padding: 1.5rem 1rem 2.5rem; }
            .login-container { padding: 2.5rem 1.5rem; }
        }

        @media (max-width: 480px) {
            .heading-main { font-size: 22px; }
            .heading-sub { font-size: 18px; }
            .logo-wrap-inner { width: 110px; height: 110px; }
            .left-logo-svg { width: 110px; height: 110px; }
        }
    </style>
</head>
<body>
<div class="page-bg"></div>
<div class="page-overlay"></div>
<div class="main-container">
<div class="content-box">

    <!-- ── LEFT: Branding ── -->
    <div class="logo-section">
        <div class="left-header">
            <div class="logo-wrap">
                <div class="logo-wrap-inner">
                    <?php
                        $svgContent = file_get_contents(__DIR__ . '/../../_shared/images/nha-logo-amin.svg');
                        echo str_replace('<svg id="eNuldLC5tMO1"', '<svg id="eNuldLC5tMO1" class="left-logo-svg"', $svgContent);
                    ?>
                </div>
            </div>
            <div class="left-headings">
                <h1 class="heading-main">National Housing Authority</h1>
                <h2 class="heading-sub">Financial Information Management System</h2>
            </div>
        </div>

        <div class="left-footer">
            <div class="nha-badge">
                <div class="badge-shield">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-3-3 1.41-1.41L11 12.17l4.59-4.58L17 9l-6 6z"/>
                    </svg>
                </div>
                <span class="badge-label">Secure Login</span>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Login form card ── -->
    <div class="login-container">
        <div class="login-form">
            <div class="form-margin">

                <div class="form-header">
                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        Secure Portal
                    </div>
                    <p class="form-subtitle">Use your NHA-issued credentials to proceed.</p>
                </div>

                <?php if (isset($_SESSION['flash_failure'])): ?>
                <div class="flash-error" id="flash-failure">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['flash_failure']) ?>
                </div>
                <?php unset($_SESSION['flash_failure']); endif; ?>

                <div class="flash-success" id="flash-success" style="<?= $pwdChanged ? 'display:flex;' : 'display:none;' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span id="flash-success-msg"><?= $pwdChanged ? 'Your password has been updated. Please sign in with your new password.' : '' ?></span>
                </div>

                <form name="flogin" id="flogin" action="/login" method="post" novalidate autocomplete="off">

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="text" name="username" id="username"
                                   placeholder=" " autocomplete="username" required>
                            <label class="field-label" for="username">Username</label>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="password" name="password" id="password"
                                   placeholder=" " autocomplete="current-password" required>
                            <label class="field-label" for="password">Password</label>
                            <button type="button" class="toggle-password" onclick="togglePassword()" title="Toggle password">
                                <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="retain-row">
                        <input type="checkbox" id="retain" name="retain_session">
                        <label for="retain">Retain Session</label>
                    </div>

                    <button type="submit" class="login-btn" id="btn-submit">
                        <span>Authenticate</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="forgot-row">
                        <button type="button" class="forgot-link" id="forgot-link">Forgot password?</button>
                    </div>

                </form>

                <form name="freset" id="freset" action="/resetpassword" method="post" novalidate autocomplete="off" style="display:none;">

                    <div class="field-group" id="email-group">
                        <div class="field-wrap">
                            <input class="field-input" type="email" name="email" id="reset-email"
                                   placeholder=" " autocomplete="email" required
                                   maxlength="100">
                            <label class="field-label" for="reset-email">Email Address</label>
                        </div>
                    </div>
                    <div class="field-error" id="email-error">Please enter a valid email address.</div>

                    <button type="submit" class="login-btn" id="btn-reset">
                        <span>Send Reset Link</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>

                    <div class="forgot-row" style="justify-content:flex-start;">
                        <button type="button" class="back-link" id="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Sign In
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div class="login-footer">
            By using this service, you agree that your information may be collected, stored, used, and
            disclosed under the Data Privacy Act of 2012 (RA No. 10173). NHA is committed to protecting
            personal information and maintaining privacy, accuracy, and security.
        </div>
    </div>

</div>
</div>

<script>
    function togglePassword() {
        const f = document.getElementById('password');
        const i = document.getElementById('togglePasswordIcon');
        if (f.type === 'password') {
            f.type = 'text';
            i.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            f.type = 'password';
            i.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    document.getElementById('flogin').addEventListener('submit', function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            alert('Please enter both username and password.');
            return;
        }

        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.innerHTML = '<span>Signing in…</span><i class="fas fa-spinner fa-spin"></i>';

        fetch('/login')
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const csrfInputs = doc.querySelectorAll('input[name*="csrf"]');
                if (csrfInputs.length >= 2) {
                    [csrfInputs[0], csrfInputs[1]].forEach(src => {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = src.name;
                        h.value = src.value;
                        this.appendChild(h);
                    });
                }
                this.submit();
            })
            .catch(() => {
                alert('Error connecting to login server. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<span>Authenticate</span><i class="fas fa-arrow-right"></i>';
            });
    });

    /* ══════════════════════════════════════
       FORGOT PASSWORD — toggle + submit flow
    ══════════════════════════════════════ */
    const loginForm  = document.getElementById('flogin');
    const resetForm  = document.getElementById('freset');
    const forgotLink = document.getElementById('forgot-link');
    const backLink   = document.getElementById('back-link');
    const emailInput = document.getElementById('reset-email');
    const emailGroup = document.getElementById('email-group');
    const emailError = document.getElementById('email-error');
    const flashOk    = document.getElementById('flash-success');
    const flashOkMsg = document.getElementById('flash-success-msg');
    const flashErr   = document.getElementById('flash-failure');

    // RFC5322-lite pattern — matches HTML5 built-in checker
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function showReset() {
        loginForm.style.display = 'none';
        resetForm.style.display = 'flex';
        if (flashErr) flashErr.style.display = 'none';
        flashOk.style.display = 'none';
        emailInput.value = '';
        clearEmailError();
        setTimeout(() => emailInput.focus(), 0);
    }

    function showLogin() {
        resetForm.style.display = 'none';
        loginForm.style.display = 'flex';
        clearEmailError();
    }

    function clearEmailError() {
        emailGroup.classList.remove('invalid');
        emailInput.classList.remove('invalid');
        emailError.classList.remove('show');
    }

    function markEmailError(msg) {
        emailError.textContent = msg;
        emailGroup.classList.add('invalid');
        emailInput.classList.add('invalid');
        emailError.classList.add('show');
    }

    forgotLink.addEventListener('click', showReset);
    backLink.addEventListener('click', showLogin);

    emailInput.addEventListener('input', clearEmailError);

    resetForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = emailInput.value.trim();

        if (!email) {
            markEmailError('Please enter your email address.');
            emailInput.focus();
            return;
        }
        if (!EMAIL_RE.test(email) || email.length > 100) {
            markEmailError('Please enter a valid email address.');
            emailInput.focus();
            return;
        }

        const btn = document.getElementById('btn-reset');
        btn.disabled = true;
        btn.innerHTML = '<span>Sending…</span><i class="fas fa-spinner fa-spin"></i>';

        // Fetch the reset-password page to harvest the actual email field
        // name (PHPMaker uses "x_email") and CSRF tokens, then POST back to it.
        fetch('/resetpassword')
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');

                // Locate PHPMaker's email input to get its real field name.
                let realEmailName = 'x_email';
                const emailField = doc.querySelector('input[id$="email"][type="text"], input[name$="email"]');
                if (emailField && emailField.name) {
                    realEmailName = emailField.name;
                }

                const fd = new FormData();
                fd.append(realEmailName, email);
                fd.append('modal', '0');

                // Copy CSRF hidden inputs (name + value pair) from the source form.
                const csrfInputs = doc.querySelectorAll('input[name*="csrf"]');
                csrfInputs.forEach(inp => fd.append(inp.name, inp.value));

                // If the source uses different field name for our email input, rebuild.
                if (realEmailName !== 'email') {
                    fd.delete('email');
                }

                return fetch('/resetpassword', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    redirect: 'follow'
                });
            })
            .then(() => {
                // Reset-password endpoint always returns a neutral response
                // regardless of whether the email exists (anti-enumeration).
                // Show a neutral inline message.
                resetForm.reset();
                showLogin();
                flashOkMsg.textContent =
                    'If your email address is registered, you will receive password ' +
                    'recovery instructions shortly.';
                flashOk.style.display = 'flex';
            })
            .catch(() => {
                markEmailError('Could not reach the server. Please try again.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<span>Send Reset Link</span><i class="fas fa-paper-plane"></i>';
            });
    });
</script>
</body>
</html>
