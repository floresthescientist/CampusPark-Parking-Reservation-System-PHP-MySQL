<?php
/*
 * receipt.php - Parking pass + payment receipt
 * Reads from the booking_details VIEW, which joins all six tables
 * (bookings, spaces, lots, vehicles, members, payments).
 * The WHERE clause also checks member_id, so you can only open
 * your own pass even if you change the number in the address bar.
 */
require 'inc/connect.php';
require 'inc/helpers.php';

must_login();

$bookingId = (int) ($_GET['b'] ?? 0);

$query = $db->prepare('SELECT * FROM booking_details
                       WHERE booking_id = :booking_id AND member_id = :me');
$query->execute([':booking_id' => $bookingId, ':me' => member_id()]);
$pass = $query->fetch();

if (!$pass) {
    set_notice('bad', 'We could not find that booking on your account.');
    go('my_bookings.php');
}

$cancelled = $pass['booking_status'] === 'Cancelled';

$title = 'Parking Pass #' . $pass['booking_id'];
require 'inc/top.php';
?>

<div class="pass-page">
    <article class="pass <?= $cancelled ? 'pass-void' : '' ?>">
        <header class="pass-top">
            <span class="pass-brand">CampusPark pass</span>
            <span class="pass-num">#<?= (int) $pass['booking_id'] ?></span>
        </header>

        <?php if ($cancelled): ?>
            <p class="pass-stamp">Cancelled</p>
        <?php endif; ?>

        <div class="pass-main">
            <div>
                <p class="pass-label">Space</p>
                <p class="pass-space"><?= h($pass['space_code']) ?></p>
                <p><?= h($pass['lot_name']) ?></p>
                <p class="soft small"><?= h($pass['location_note']) ?></p>
            </div>
            <div class="pass-car">
                <p class="pass-label">Vehicle</p>
                <p class="plate plate-big"><?= h($pass['plate_number']) ?></p>
                <p class="small"><?= h($pass['color']) ?> <?= h($pass['make_model']) ?></p>
            </div>
        </div>

        <dl class="pass-rows">
            <div><dt>Driver</dt><dd><?= h($pass['first_name'] . ' ' . $pass['last_name']) ?></dd></div>
            <div><dt>Date</dt><dd><?= h(show_date($pass['starts_at'])) ?></dd></div>
            <div><dt>Arrive</dt><dd><?= h(show_time($pass['starts_at'])) ?></dd></div>
            <div><dt>Leave by</dt><dd><?= h(show_time($pass['ends_at'])) ?></dd></div>
            <div><dt>Space type</dt><dd><?= h($pass['space_type']) ?></dd></div>
            <div><dt>Booked on</dt><dd><?= h(date('M j, g:i a', strtotime($pass['booked_on']))) ?></dd></div>
        </dl>

        <footer class="pass-pay">
            <?php if ($pass['amount_paid'] !== null): ?>
                <span><?= money($pass['amount_paid']) ?> &middot; card ending <?= h($pass['card_last4']) ?></span>
                <span class="tag <?= $pass['payment_status'] === 'Paid' ? 'tag-open' : 'tag-closed' ?>"><?= h($pass['payment_status']) ?></span>
            <?php else: ?>
                <span>No payment on file</span>
            <?php endif; ?>
        </footer>
    </article>

    <div class="pass-actions">
        <button type="button" class="btn btn-dark" data-print>Print pass</button>
        <a href="my_bookings.php" class="btn btn-ghost-dark">My bookings</a>
        <a href="find.php" class="btn btn-ghost-dark">Book another</a>
    </div>
</div>

<?php require 'inc/bottom.php'; ?>
