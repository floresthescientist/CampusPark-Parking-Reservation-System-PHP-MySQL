<?php
/*
 * signup.php - Create an account
 * INSERT INTO members. The password is hashed with password_hash()
 * so the real password is never saved in the database.
 */
require 'inc/connect.php';
require 'inc/helpers.php';

if (member_id()) {
    go('my_bookings.php');
}

$problems  = [];
$firstName = '';
$lastName  = '';
$email     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!form_key_ok()) {
        $problems[] = 'This form expired. Please try again.';
    }

    $firstName = clean('first_name');
    $lastName  = clean('last_name');
    $email     = strtolower(clean('email'));
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    $namePattern = "/^[A-Za-zÀ-ÿ' -]{1,50}$/u";
    if (!preg_match($namePattern, $firstName)) {
        $problems[] = 'First name can only use letters, spaces, hyphens and apostrophes.';
    }
    if (!preg_match($namePattern, $lastName)) {
        $problems[] = 'Last name can only use letters, spaces, hyphens and apostrophes.';
    }
    if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $problems[] = 'Enter a real email address.';
    }
    if (!is_string($password) || strlen($password) < 8 || !preg_match('/\d/', $password) || !preg_match('/[A-Za-z]/', $password)) {
        $problems[] = 'Your password needs at least 8 characters, with at least one letter and one number.';
    } elseif ($password !== $password2) {
        $problems[] = 'The two passwords do not match.';
    }

    if (!$problems) {
        $check = $db->prepare('SELECT member_id FROM members WHERE email = :email');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $problems[] = 'There is already an account with that email. Try logging in.';
        }
    }

    if (!$problems) {
        $add = $db->prepare('INSERT INTO members (first_name, last_name, email, password_hash)
                             VALUES (:first, :last, :email, :hash)');
        $add->execute([
            ':first' => $firstName,
            ':last'  => $lastName,
            ':email' => $email,
            ':hash'  => password_hash($password, PASSWORD_DEFAULT),
        ]);

        // Log the new member in right away
        session_regenerate_id(true);
        $_SESSION['member_id']  = (int) $db->lastInsertId();
        $_SESSION['first_name'] = $firstName;

        set_notice('ok', "Welcome to CampusPark, $firstName! Add your car to start booking.");
        go('my_vehicles.php');
    }
}

$title = 'Sign Up';
require 'inc/top.php';
?>

<div class="auth">
    <section class="panel auth-panel">
        <h1>Create your account</h1>
        <p class="soft">Takes less than a minute. Already have one? <a href="login.php">Log in</a>.</p>

        <?php if ($problems): ?>
            <div class="notice notice-bad">
                <?php foreach ($problems as $problem): ?><p><?= h($problem) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="signup.php" class="stack-form" data-check>
            <?= form_key_input() ?>
            <div class="field-pair">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" maxlength="50" required value="<?= h($firstName) ?>" autocomplete="given-name">
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" maxlength="50" required value="<?= h($lastName) ?>" autocomplete="family-name">
                </div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" required value="<?= h($email) ?>" autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                    <button type="button" class="show-pass" data-target="password">Show</button>
                </div>
                <small id="password-hint" class="hint">8+ characters with a letter and a number.</small>
            </div>
            <div class="field">
                <label for="password2">Type it again</label>
                <input type="password" id="password2" name="password2" minlength="8" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-yellow btn-wide">Create account</button>
        </form>
    </section>
</div>

<?php require 'inc/bottom.php'; ?>
