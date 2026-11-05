<?php
/*
 * login.php - Log in with email + password
 * password_verify() compares the typed password with the saved hash.
 * The error message is the same for a wrong email or a wrong password,
 * so nobody can use this form to find out who has an account.
 */
require 'inc/connect.php';
require 'inc/helpers.php';

if (member_id()) {
    go('my_bookings.php');
}

$email   = '';
$problem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(clean('email'));
    $password = $_POST['password'] ?? '';

    if (!form_key_ok()) {
        $problem = 'This form expired. Please try again.';
    } elseif ($email === '' || !is_string($password) || $password === '') {
        $problem = 'Enter your email and password.';
    } else {
        $find = $db->prepare('SELECT member_id, first_name, password_hash FROM members WHERE email = :email');
        $find->execute([':email' => $email]);
        $member = $find->fetch();

        if ($member && password_verify($password, $member['password_hash'])) {
            // New session ID after login (stops session fixation)
            session_regenerate_id(true);
            $_SESSION['member_id']  = (int) $member['member_id'];
            $_SESSION['first_name'] = $member['first_name'];

            // Go back to the page they were trying to open, if any
            // (only paths on this site are allowed, never another website)
            $next = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if (!is_string($next) || !preg_match('#^/(?![/\\\\])#', $next)) {
                $next = 'my_bookings.php';
            }

            set_notice('ok', 'Welcome back, ' . $member['first_name'] . '!');
            go($next);
        }
        $problem = 'That email and password don\'t match an account.';
    }
}

$title = 'Log In';
require 'inc/top.php';
?>

<div class="auth">
    <section class="panel auth-panel">
        <h1>Log in</h1>
        <p class="soft">New here? <a href="signup.php">Create an account</a>.</p>

        <?php if ($problem): ?>
            <p class="notice notice-bad"><?= h($problem) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" class="stack-form" data-check>
            <?= form_key_input() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= h($email) ?>" autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="show-pass" data-target="password">Show</button>
                </div>
            </div>
            <button type="submit" class="btn btn-dark btn-wide">Log in</button>
        </form>

        <p class="demo-box">Demo account: <code>demo@campuspark.test</code> / <code>Park1234!</code></p>
    </section>
</div>

<?php require 'inc/bottom.php'; ?>
