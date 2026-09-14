<?php
require_once __DIR__ . "/src/db_config.php";
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = "";
$success = "";

/**
 * Attempts a login for the posted username/password.
 * Sets all session variables the rest of the portal (header.php,
 * sidebar.php role checks, auth_handler.php) depends on, then redirects
 * to dashboard.php on success. Returns an error message string on failure,
 * or null on success (in which case this function never returns — it exits).
 */
function attemptLogin(mysqli $conn): ?string {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        return "Please enter your username and password.";
    }

    $stmt = mysqli_prepare($conn,
        "SELECT user_id, username, password, full_name, email, phone_number, profile_photo, role, status
         FROM users WHERE username = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);

    if (!$user || !password_verify($password, $user['password'])) {
        return "Invalid username or password.";
    }

    if ($user['status'] === 'inactive') {
        return "Your account has been deactivated. Please contact the administrator.";
    }

    // Populate every session variable the portal relies on: header.php,
    // sidebar.php's admin-only "Account Manager" check, and profile updates.
    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['full_name']   = $user['full_name'];
    $_SESSION['email']       = $user['email'] ?? '';
    $_SESSION['phone']       = $user['phone_number'] ?? '';
    $_SESSION['photo']       = $user['profile_photo'] ?? '';
    $_SESSION['role']        = $user['role'];

    header("Location: dashboard.php");
    exit();
}

/**
 * Handles the "forgot password" flow: always shows the same generic
 * success message (so we don't leak which emails exist), but only
 * actually issues a reset token when the email matches a real account.
 */
function attemptPasswordReset(mysqli $conn): string {
    $recovery_email = trim($_POST['recovery_email'] ?? '');

    if (empty($recovery_email)) {
        return "Please enter your recovery email.";
    }

    $stmt = mysqli_prepare($conn,
        "SELECT user_id, full_name, recovery_email FROM users WHERE recovery_email = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $recovery_email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);

    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $uid     = $user['user_id'];

        $del = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
        mysqli_stmt_bind_param($del, "i", $uid);
        mysqli_stmt_execute($del);

        $ins = mysqli_prepare($conn,
            "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, "iss", $uid, $token, $expires);
        mysqli_stmt_execute($ins);

        // TODO: wire up mail() or PHPMailer here
        // $link = "https://yourdomain.com/reset_password.php?token=" . $token;
        // mail($recovery_email, "Password Reset — CASD", "Reset link: " . $link);
    }

    return "If that recovery email exists, a reset link has been sent.";
}

// ─────────────────────────────────────────────────────────────
// DISPATCH
// ─────────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $error = attemptLogin($conn);
}

if (isset($_POST['forgot_password'])) {
    $success = attemptPasswordReset($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASD — Corn Team Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* core palette — deep husk green, ripe-corn gold, cream-silk highlight */
            --green:       #1a6b3c;
            --green-dark:  #0d3d1f;
            --green-hover: #155c32;
            --gold:        #c8960c;
            --gold-light:  #e0b94a;
            --silk:        #f7ecc9;
            --white:       #ffffff;
            --off-white:   #f8f7f2;
            --label:       #6b7280;
            --text:        #20291f;
            --border:      #e6e3d8;
            --error-bg:    #fef2f2;
            --error-text:  #b91c1c;
            --success-bg:  #f0fdf4;
            --success-text:#15803d;
            --input-bg:    #f9faf6;

            --font-display: 'Fraunces', serif;
            --font-body:    'Outfit', sans-serif;
        }

        html, body {
            height: 100%;
            font-family: var(--font-body);
            background: var(--green-dark);
        }

        /* ── LAYOUT ── */
        .page {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* ── LEFT — photo panel ── */
        .left-panel {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0d3d1c 0%, #1a6b3c 45%, #2d9e5a 100%);
        }

        .left-panel img.bg-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        /* dark gradient overlay on photo for legibility + mood */
        .left-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at center, rgba(10,40,18,.15) 0%, rgba(8,26,14,.62) 78%),
                linear-gradient(180deg, rgba(8,26,14,.35) 0%, transparent 30%, transparent 70%, rgba(8,26,14,.55) 100%);
        }

        /* thin gold seam where the two panels meet */
        .left-panel::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, transparent, var(--gold-light) 45%, var(--gold) 55%, transparent);
            z-index: 3;
            opacity: .85;
        }

        /* ── RIGHT — login panel ── */
        .right-panel {
            width: 440px;
            flex-shrink: 0;
            background: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 44px;
            position: relative;
            overflow-y: auto;
            box-shadow: -8px 0 40px rgba(0,0,0,.18);
        }

        /* top accent line */
        .right-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green) 0%, var(--gold) 100%);
        }

        /* ── LOGO AREA ── */
        .brand {
            text-align: center;
            margin-bottom: 34px;
            width: 100%;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            margin: 0 auto 14px;
        }

        .brand-name {
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.28;
            letter-spacing: -.01em;
        }

        .brand-sub {
            font-size: 11.5px;
            color: var(--gold);
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
            letter-spacing: .14em;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }

        .brand-sub::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 28px;
            height: 2px;
            background: linear-gradient(90deg, var(--green), var(--gold));
            border-radius: 2px;
        }

        /* ── ALERTS ── */
        .alert {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .alert-error   { background: var(--error-bg);   color: var(--error-text); }
        .alert-success { background: var(--success-bg);  color: var(--success-text); }

        /* ── FORM ── */
        .form-wrap { width: 100%; }

        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--label);
            margin-bottom: 6px;
        }

        .input-row {
            position: relative;
        }

        .input-row input {
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px 46px 12px 14px;
            font-size: 14px;
            font-family: var(--font-body);
            font-weight: 500;
            color: var(--text);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .input-row input:focus {
            border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(26,107,60,.1);
        }

        .input-row input:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 1px;
        }

        /* SHOW/HIDE password toggle */
        .show-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 11px;
            font-weight: 700;
            color: var(--green);
            cursor: pointer;
            letter-spacing: .04em;
            font-family: var(--font-body);
            padding: 0;
        }

        .show-btn:hover { color: var(--green-dark); }

        /* ── FORGOT LINK row ── */
        .meta-row {
            display: flex;
            justify-content: flex-end;
            margin-top: -6px;
            margin-bottom: 20px;
        }

        .link-btn {
            background: none;
            border: none;
            font-family: var(--font-body);
            font-size: 12.5px;
            font-weight: 600;
            color: var(--green);
            cursor: pointer;
            padding: 0;
            text-decoration: none;
        }

        .link-btn:hover { text-decoration: underline; color: var(--green-dark); }

        /* ── PRIMARY BUTTON ── */
        .btn-login {
            width: 100%;
            background: var(--green);
            color: var(--white);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            text-transform: uppercase;
        }

        .btn-login:hover {
            background: var(--green-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(26,107,60,.25);
        }

        .btn-login:active { transform: translateY(0); box-shadow: none; }

        /* ── FORGOT PANEL ── */
        #forgot-panel { display: none; width: 100%; }

        #forgot-panel.active {
            display: block;
            animation: fadeIn .3s ease both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            color: var(--label);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            padding: 0;
            margin-bottom: 22px;
        }

        .back-btn:hover { color: var(--text); }

        .info-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 12.5px;
            color: #92400e;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .btn-ghost {
            width: 100%;
            background: transparent;
            color: var(--green);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background .2s, border-color .2s;
        }

        .btn-ghost:hover { background: var(--off-white); border-color: var(--green); }

        /* footer */
        .footer {
            position: absolute;
            bottom: 16px;
            font-size: 11px;
            color: #d1d5db;
            text-align: center;
            width: 100%;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            .left-panel { display: none; }
            .right-panel { width: 100%; box-shadow: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .btn-login { transition: none; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- ════════════════════════════════════
         LEFT — field photo
    ════════════════════════════════════ -->
    <div class="left-panel">
        <img
            class="bg-photo"
            src="bg.png"
            alt="Corn field"
            onerror="this.style.display='none'"
        >
    </div>

    <!-- ════════════════════════════════════
         RIGHT — login panel
    ════════════════════════════════════ -->
    <div class="right-panel">

        <!-- BRAND -->
        <div class="brand">
            <svg class="brand-logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="CASD logo">
                <defs>
                    <linearGradient id="goldGrainSm" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#e0b94a"/>
                        <stop offset="100%" stop-color="#c8960c"/>
                    </linearGradient>
                    <linearGradient id="greenBladeSm" x1="0%" y1="100%" x2="0%" y2="0%">
                        <stop offset="0%" stop-color="#134d2b"/>
                        <stop offset="100%" stop-color="#1a6b3c"/>
                    </linearGradient>
                </defs>
                <path d="M50 96 C 30 78, 14 58, 20 30 C 30 46, 42 60, 50 70 Z" fill="url(#greenBladeSm)"/>
                <path d="M50 96 C 70 78, 86 58, 80 30 C 70 46, 58 60, 50 70 Z" fill="url(#greenBladeSm)"/>
                <g fill="url(#goldGrainSm)">
                    <ellipse cx="50" cy="18" rx="4.4" ry="8.5"/>
                    <ellipse cx="40" cy="21" rx="4.1" ry="8" transform="rotate(-16 40 21)"/>
                    <ellipse cx="60" cy="21" rx="4.1" ry="8" transform="rotate(16 60 21)"/>
                    <ellipse cx="32" cy="28" rx="3.8" ry="7.4" transform="rotate(-32 32 28)"/>
                    <ellipse cx="68" cy="28" rx="3.8" ry="7.4" transform="rotate(32 68 28)"/>
                </g>
                <path d="M50 66 C 46 60, 37 60, 37 68 C 37 75, 50 84, 50 84 C 50 84, 63 75, 63 68 C 63 60, 54 60, 50 66 Z" fill="url(#goldGrainSm)"/>
            </svg>
            <div class="brand-name">City Agricultural Services<br>Department</div>
            <div class="brand-sub">Corn Team Portal</div>
        </div>

        <!-- ALERTS -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- ── LOGIN FORM ── -->
        <div id="login-panel" class="form-wrap">

            <form method="POST" autocomplete="on">

                <div class="field">
                    <label>Username</label>
                    <div class="input-row">
                        <input
                            type="text"
                            name="username"
                            placeholder="Enter Username"
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="input-row">
                        <input
                            type="password"
                            name="password"
                            id="pw-field"
                            placeholder="Enter Password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="show-btn" onclick="togglePw()">SHOW</button>
                    </div>
                </div>

                <div class="meta-row">
                    <button type="button" class="link-btn" onclick="showForgot()">
                        Forgot password?
                    </button>
                </div>

                <button type="submit" name="login" class="btn-login">
                    Login
                </button>

            </form>

        </div>

        <!-- ── FORGOT PASSWORD FORM ── -->
        <div id="forgot-panel">

            <button type="button" class="back-btn" onclick="showLogin()">
                ← Back to Login
            </button>

            <div class="brand" style="margin-bottom:16px;">
                <div class="brand-name" style="font-size:18px;">Reset Password</div>
                <div class="brand-sub" style="color:var(--label);">Enter your recovery email to get a reset link.</div>
            </div>

            <div class="info-box">
                💡 Your recovery email must match the one saved by your administrator. Contact admin if you haven't set one yet.
            </div>

            <form method="POST" class="form-wrap">
                <div class="field">
                    <label>Recovery Email</label>
                    <div class="input-row">
                        <input
                            type="email"
                            name="recovery_email"
                            placeholder="your-email@gmail.com"
                            required
                        >
                    </div>
                </div>

                <button type="submit" name="forgot_password" class="btn-login">
                    Send Reset Link
                </button>

                <button type="button" class="btn-ghost" onclick="showLogin()">
                    Cancel
                </button>
            </form>

        </div>

        <div class="footer">
            © <?= date('Y') ?> City Agricultural Services Department
        </div>

    </div><!-- /right-panel -->

</div><!-- /page -->

<script>
    // Toggle password visibility
    function togglePw() {
        const f   = document.getElementById('pw-field');
        const btn = f.nextElementSibling;
        if (f.type === 'password') {
            f.type   = 'text';
            btn.textContent = 'HIDE';
        } else {
            f.type   = 'password';
            btn.textContent = 'SHOW';
        }
    }

    // Switch panels
    function showForgot() {
        document.getElementById('login-panel').style.display  = 'none';
        document.getElementById('forgot-panel').classList.add('active');
    }

    function showLogin() {
        document.getElementById('forgot-panel').classList.remove('active');
        document.getElementById('login-panel').style.display  = 'block';
    }

    // Auto-show forgot panel after a forgot_password POST
    <?php if (isset($_POST['forgot_password'])): ?>
        showForgot();
    <?php endif; ?>
</script>

</body>
</html>