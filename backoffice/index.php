<?php
require_once __DIR__ . '/lib/bootstrap.php';
sarah_ensure_dirs();
if (current_user()) redirect('dashboard.php');

$error = '';
$step2 = isset($_SESSION['pending_user']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_step']) && $_POST['login_step'] === '1') {
        if (!captcha_check($_POST['captcha'] ?? '')) {
            $error = 'CAPTCHA incorreto.';
        } else {
            $u = user_by_username(trim($_POST['username'] ?? ''));
            if (!$u || !password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
                $error = 'Credenciais inválidas.';
                audit_log('login_failed', $_POST['username'] ?? '');
            } else {
                if (defined('SARAH_REQUIRE_2FA') && SARAH_REQUIRE_2FA && !empty($u['totp_secret'])) {
                    $_SESSION['pending_user'] = $u['username'];
                    $step2 = true;
                } else {
                    $_SESSION['user'] = array('username'=>$u['username'],'role'=>$u['role']);
                    audit_log('login_ok');
                    redirect('dashboard.php');
                }
            }
        }
    } elseif (isset($_POST['login_step']) && $_POST['login_step'] === '2') {
        $u = user_by_username($_SESSION['pending_user'] ?? '');
        if (!$u || !verify_totp($u['totp_secret'], $_POST['totp'] ?? '')) {
            $error = 'Código 2FA inválido.';
        } else {
            unset($_SESSION['pending_user']);
            $_SESSION['user'] = array('username'=>$u['username'],'role'=>$u['role']);
            audit_log('login_ok_2fa');
            redirect('dashboard.php');
        }
    }
}
app_header('SARAH Login');
?>
<h1>SARAH Backoffice</h1>
<div class="card" style="max-width:520px">
<?php if ($error): ?><p class="errText"><?=h($error)?></p><?php endif; ?>
<?php if ($step2): ?>
<form method="post">
<input type="hidden" name="login_step" value="2">
<label>Código 2FA</label><input name="totp" inputmode="numeric" autocomplete="one-time-code">
<button class="primary">Entrar</button>
</form>
<p><a href="logout.php">Cancelar</a></p>
<?php else: ?>
<form method="post">
<input type="hidden" name="login_step" value="1">
<label>Utilizador</label><input name="username" autocomplete="username">
<label>Password</label><input name="password" type="password" autocomplete="current-password">
<?php if (defined('SARAH_ENABLE_CAPTCHA') && SARAH_ENABLE_CAPTCHA): ?>
<label>CAPTCHA: quanto é <?=h(captcha_question())?>?</label><input name="captcha" inputmode="numeric">
<?php endif; ?>
<button class="primary">Entrar</button>
</form>
<p><a href="forgot_password.php">Esqueci-me da password</a></p>
<?php endif; ?>
</div>
<?php app_footer(); ?>
