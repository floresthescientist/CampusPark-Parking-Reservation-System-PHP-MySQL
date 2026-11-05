<?php
/*
 * my_bookings.php - Everything the logged-in member has booked
 * Upcoming bookings are shown as cards (with Cancel buttons),
 * older or cancelled ones go in a history table.
 *
 * JOINs used:
 *   bookings INNER JOIN vehicles (to know whose booking it is)
 *            INNER JOIN spaces   (space code)
 *            INNER JOIN lots     (lot name)
 *            LEFT  JOIN payments (amount + paid/refunded)
 */
require 'inc/connect.php';
require 'inc/helpers.php';

must_login();

$sql = "SELECT b.booking_id, b.starts_at, b.ends_at, b.booking_status,
               s.space_code, l.lot_name,
               v.plate_number,
               p.amount_paid, p.payment_status
        FROM bookings b
        INNER JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        INNER JOIN spaces   s ON s.space_id   = b.space_id
        INNER JOIN lots     l ON l.lot_id     = s.lot_id
        LEFT  JOIN payments p ON p.booking_id = b.booking_id
        WHERE v.member_id = :me
        ORDER BY b.starts_at DESC";
$query = $db->prepare($sql);
$query->execute([':me' => member_id()]);
$all = $query->fetchAll();

// Split into "coming up" and "history" in PHP
$now = date('Y-m-d H:i:s');
$upcoming = [];
$history  = [];
foreach ($all as $row) {
    if ($row['booking_status'] === 'Active' && $row['ends_at'] > $now) {
        $upcoming[] = $row;
    } else {
        $history[] = $row;
    }
}
// soonest first for the upcoming list
$upcoming = array_reverse($upcoming);

$title = 'My Bookings';
require 'inc/top.php';
?>

<div class="title-row">
    <h1 class="page-title">My bookings</h1>
    <a class="btn btn-dark" href="find.php">+ New booking</a>
</div>

<h2 class="section-title">Coming up</h2>
<?php if (!$upcoming): ?>
    <p class="empty-hint">Nothing booked yet. <a href="find.php">Find a space</a> for your next class.</p>
<?php else: ?>
    <div class="booking-cards">
        <?php foreach ($upcoming as $row):
            $started = $row['starts_at'] <= $now;
        ?>
            <article class="booking-card">
                <div class="booking-card-space">
                    <span class="booking-card-code"><?= h($row['space_code']) ?></span>
                    <span class="small"><?= h($row['lot_name']) ?></span>
                </div>
                <div class="booking-card-info">
                    <p><strong><?= h(show_date($row['starts_at'])) ?></strong></p>
                    <p><?= h(show_time($row['starts_at'])) ?> &ndash; <?= h(show_time($row['ends_at'])) ?></p>
                    <p class="small soft">Car <span class="plate"><?= h($row['plate_number']) ?></span> &middot; <?= money($row['amount_paid']) ?></p>
                    <?php if ($started): ?><span class="tag tag-open">Parked now</span><?php endif; ?>
                </div>
                <div class="booking-card-actions">
                    <a class="btn btn-small btn-ghost-dark" href="receipt.php?b=<?= (int) $row['booking_id'] ?>">Pass</a>
                    <?php if (!$started): ?>
                        <form method="post" action="cancel_booking.php" class="js-cancel">
                            <?= form_key_input() ?>
                            <input type="hidden" name="booking_id" value="<?= (int) $row['booking_id'] ?>">
                            <button type="submit" class="btn btn-small btn-red">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="section-title">History</h2>
<?php if (!$history): ?>
    <p class="empty-hint">Past and cancelled bookings will show up here.</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="grid-table">
            <thead>
                <tr><th>Pass</th><th>Date</th><th>Space</th><th>Car</th><th>Paid</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td data-col="Pass"><a href="receipt.php?b=<?= (int) $row['booking_id'] ?>">#<?= (int) $row['booking_id'] ?></a></td>
                        <td data-col="Date"><?= h(show_date($row['starts_at'])) ?>, <?= h(show_time($row['starts_at'])) ?></td>
                        <td data-col="Space"><?= h($row['space_code']) ?> &middot; <?= h($row['lot_name']) ?></td>
                        <td data-col="Car"><span class="plate"><?= h($row['plate_number']) ?></span></td>
                        <td data-col="Paid"><?= $row['amount_paid'] !== null ? money($row['amount_paid']) : '&ndash;' ?></td>
                        <td data-col="Status">
                            <?php if ($row['booking_status'] === 'Cancelled'): ?>
                                <span class="tag tag-closed">Cancelled &middot; <?= h($row['payment_status'] ?? '') ?></span>
                            <?php else: ?>
                                <span class="tag tag-done">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'inc/bottom.php'; ?>
