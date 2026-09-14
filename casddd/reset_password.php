<?php
session_start();
$token = $_GET['token'] ?? '';
$error = $_GET['error'] ?? '';

if (empty($token)) {
    header("Location: index.php?error=" . urlencode("Invalid or missing reset link."));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - City Agricultural Services Department</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }

  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1f4d2c 0%, #2e7d32 50%, #4a7c2f 100%);
  }

  .card {
    width: 380px;
    background: #fff;
    border-radius: 10px;
    padding: 36px 32px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  }

  .card h1 { font-size: 17px; color: #1a1a1a; text-align: center; margin-bottom: 4px; }

  .card .subtitle { font-size: 12.5px; color: #6b6b6b; margin-bottom: 24px; text-align: center; }

  .field-group { margin-bottom: 16px; }

  .field-group label { display: block; font-size: 12.5px; color: #333; margin-bottom: 6px; font-weight: 600; }

  .field-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d5d5d5;
    border-radius: 6px;
    font-size: 13.5px;
    outline: none;
  }

  .field-group input:focus { border-color: #2e7d32; }

  .hint { font-size: 11px; color: #888; margin-top: 6px; line-height: 1.4; }

  .submit-btn {
    width: 100%;
    padding: 11px;
    background: #2e7d32;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 8px;
  }

  .submit-btn:hover { background: #256428; }

  .alert-error {
    background: #fdecea;
    color: #b3261e;
    border: 1px solid #f5c6c3;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 12.5px;
    margin-bottom: 16px;
    text-align: center;
  }

  .back-link { display: block; text-align: center; margin-top: 16px; font-size: 12.5px; }
  .back-link a { color: #2e7d32; font-weight: 600; text-decoration: none; }
  .back-link a:hover { text-decoration: underline; }
</style>
</head>
<body>

  <div class="card">
    <h1>Set a New Password</h1>
    <div class="subtitle">City Agricultural Services Department — Corn Team Portal</div>

    <?php if ($error): ?>
      <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="src/auth.php">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

      <div class="field-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
        <div class="hint">At least 8 characters, with an uppercase letter, lowercase letter, number, and symbol.</div>
      </div>

      <div class="field-group">
        <label for="confirm_new_password">Confirm New Password</label>
        <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="Re-enter new password" required>
      </div>

      <button type="submit" class="submit-btn">RESET PASSWORD</button>
    </form>

    <div class="back-link">
      <a href="index.php">Back to Login</a>
    </div>
  </div>

</body>
</html>
