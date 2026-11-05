<?php
/*
 * report.php - Lot Report
 * Summary numbers built entirely with JOINs + GROUP BY.
 *   1) Each lot:   lots LEFT JOIN spaces LEFT JOIN bookings LEFT JOIN payments
 *   2) Space type: spaces LEFT JOIN bookings
 *   3) Top spaces: bookings INNER JOIN spaces INNER JOIN lots
 * Cancelled bookings and refunded payments are left out.
 */
require 'inc/connect.php';
require 'inc/helpers.php';

// 1) Totals per lot
$byLot = $db->query(
    "SELECT l.lot_name,
            COUNT(DISTINCT s.space_id)   AS spaces,
            COUNT(DISTINCT b.booking_id) AS bookings,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, b.starts_at, b.ends_at)) / 60, 0) AS hours_booked,
            COALESCE(SUM(p.amount_paid), 0) AS revenue
     FROM lots l
     LEFT JOIN spaces s   ON s.lot_id = l.lot_id
     LEFT JOIN bookings b ON b.space_id = s.space_id AND b.booking_status = 'Active'
     LEFT JOIN payments p ON p.booking_id = b.booking_id AND p.payment_status = 'Paid'
     GROUP BY l.lot_id, l.lot_name
     ORDER BY revenue DESC"
)->fetchAll();

// 2) Bookings per space type
$byType = $db->query(
    "SELECT s.space_type,
            COUNT(DISTINCT s.space_id) AS spaces,
            COUNT(b.booking_id)        AS bookings
     FROM spaces s
     LEFT JOIN bookings b ON b.space_id = s.space_id AND b.booking_status = 'Active'
     GROUP BY s.space_type
     ORDER BY bookings DESC"
)->fetchAll();

// 3) Five most-booked spaces
$topSpaces = $db->query(
    "SELECT s.space_code, s.space_type, l.lot_name, COUNT(b.booking_id) AS bookings
     FROM bookings b
     INNER JOIN spaces s ON s.space_id = b.space_id
     INNER JOIN lots l   ON l.lot_id   = s.lot_id
     WHERE b.booking_status = 'Active'
     GROUP BY s.space_id, s.space_code, s.space_type, l.lot_name
     ORDER BY bookings DESC, s.space_code
     LIMIT 5"
)->fetchAll();

// Grand totals and the biggest numbers (used to size the bars)
$totalRevenue  = 0;
$totalBookings = 0;
$maxRevenue    = 0;
foreach ($byLot as $row) {
    $totalRevenue  += $row['revenue'];
    $totalBookings += $row['bookings'];
    $maxRevenue     = max($maxRevenue, $row['revenue']);
}
$maxTypeBookings = 0;
foreach ($byType as $row) {
    $maxTypeBookings = max($maxTypeBookings, $row['bookings']);
}

$title = 'Lot Report';
require 'inc/top.php';
?>

<h1 class="page-title">Lot report</h1>
<p class="soft">Live totals from every confirmed booking. Cancelled bookings and refunds are not counted.</p>

<div class="big-numbers">
    <div><span><?= money($totalRevenue) ?></span>Revenue</div>
    <div><span><?= $totalBookings ?></span>Bookings</div>
    <div><span><?= count($byLot) ?></span>Lots</div>
</div>

<section class="panel">
    <h2>Revenue by lot</h2>
    <div class="table-scroll">
        <table class="grid-table">
            <thead>
                <tr><th>Lot</th><th>Spaces</th><th>Bookings</th><th>Hours</th><th>Revenue</th><th class="bar-col"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($byLot as $row):
                    $width = $maxRevenue > 0 ? round($row['revenue'] / $maxRevenue * 100) : 0;
                ?>
                    <tr>
                        <td data-col="Lot"><strong><?= h($row['lot_name']) ?></strong></td>
                        <td data-col="Spaces"><?= (int) $row['spaces'] ?></td>
                        <td data-col="Bookings"><?= (int) $row['bookings'] ?></td>
                        <td data-col="Hours"><?= h(rtrim(rtrim(number_format((float) $row['hours_booked'], 1), '0'), '.')) ?></td>
                        <td data-col="Revenue"><?= money($row['revenue']) ?></td>
                        <td class="bar-col"><span class="bar" style="width: <?= $width ?>%"></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="split">
    <section class="panel">
        <h2>Bookings by space type</h2>
        <ul class="bar-list">
            <?php foreach ($byType as $row):
                $width = $maxTypeBookings > 0 ? round($row['bookings'] / $maxTypeBookings * 100) : 0;
            ?>
                <li>
                    <span class="bar-list-label"><?= h($row['space_type']) ?> <small>(<?= (int) $row['spaces'] ?> spaces)</small></span>
                    <span class="bar-track"><span class="bar" style="width: <?= $width ?>%"></span></span>
                    <span class="bar-list-num"><?= (int) $row['bookings'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel">
        <h2>Most-booked spaces</h2>
        <?php if (!$topSpaces): ?>
            <p class="soft">No bookings yet.</p>
        <?php else: ?>
            <ol class="top-list">
                <?php foreach ($topSpaces as $row): ?>
                    <li>
                        <span class="top-code"><?= h($row['space_code']) ?></span>
                        <span><?= h($row['lot_name']) ?> <small class="soft">&middot; <?= h($row['space_type']) ?></small></span>
                        <span class="top-num"><?= (int) $row['bookings'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>

<?php require 'inc/bottom.php'; ?>
