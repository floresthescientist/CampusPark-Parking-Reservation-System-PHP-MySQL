<?php
/*
 * index.php - Home page
 * Lists every parking lot with its price, hours and how many
 * spaces are free at this moment.
 *
 * JOIN used:
 *   lots  INNER JOIN spaces   (every space belongs to a lot)
 *         LEFT JOIN  bookings (only bookings happening right now,
 *                              LEFT so spaces with no booking still count)
 */
require 'inc/connect.php';
require 'inc/helpers.php';

$now = date('Y-m-d H:i:s');

$sql = "SELECT l.lot_id, l.lot_name, l.location_note, l.price_per_hour,
               l.opens_at, l.closes_at,
               COUNT(s.space_id)   AS total_spaces,
               COUNT(b.booking_id) AS in_use_now
        FROM lots l
        INNER JOIN spaces s
                ON s.lot_id = l.lot_id
        LEFT JOIN bookings b
                ON b.space_id = s.space_id
               AND b.booking_status = 'Active'
               AND b.starts_at <= :now_a
               AND b.ends_at   >  :now_b
        GROUP BY l.lot_id, l.lot_name, l.location_note, l.price_per_hour, l.opens_at, l.closes_at
        ORDER BY l.lot_name";

$query = $db->prepare($sql);
$query->execute([':now_a' => $now, ':now_b' => $now]);
$lots = $query->fetchAll();

$title = 'Campus Lots';
require 'inc/top.php';
?>

<section class="welcome">
    <div class="welcome-text">
        <p class="eyebrow">Lehman College &middot; Bronx, NY</p>
        <h1>Your parking space is waiting before you even leave home.</h1>
        <p>Pick a lot, choose your time, and pay online. Show your parking pass when you arrive.</p>
        <div class="welcome-actions">
            <a href="find.php" class="btn btn-yellow">Find a space</a>
            <?php if (!member_id()): ?>
                <a href="signup.php" class="btn btn-ghost">Create an account</a>
            <?php endif; ?>
        </div>
    </div>

    <ol class="how-steps">
        <li><strong>Add your car</strong><span>Sign up and register your license plate.</span></li>
        <li><strong>Pick a space</strong><span>Choose a lot, a day and how many hours.</span></li>
        <li><strong>Pay &amp; park</strong><span>Get a digital pass with your space number.</span></li>
    </ol>
</section>

<h2 class="section-title">Lots right now <small><?= h(date('g:i a')) ?></small></h2>

<div class="lot-list">
    <?php foreach ($lots as $lot):
        $free    = $lot['total_spaces'] - $lot['in_use_now'];
        $percent = $lot['total_spaces'] > 0 ? round($free / $lot['total_spaces'] * 100) : 0;
        $isOpen  = date('H:i:s') >= $lot['opens_at'] && date('H:i:s') < $lot['closes_at'];
    ?>
        <article class="lot-card">
            <header class="lot-card-top">
                <h3><?= h($lot['lot_name']) ?></h3>
                <span class="tag <?= $isOpen ? 'tag-open' : 'tag-closed' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span>
            </header>
            <p class="lot-where"><?= h($lot['location_note']) ?></p>

            <dl class="lot-facts">
                <div><dt>Price</dt><dd><?= money($lot['price_per_hour']) ?>/hr</dd></div>
                <div><dt>Hours</dt><dd><?= h(show_time($lot['opens_at'])) ?> &ndash; <?= h(show_time($lot['closes_at'])) ?></dd></div>
            </dl>

            <div class="meter" aria-label="<?= $free ?> of <?= $lot['total_spaces'] ?> spaces free">
                <div class="meter-fill" style="width: <?= $percent ?>%"></div>
            </div>
            <p class="meter-label"><strong><?= $free ?></strong> of <?= $lot['total_spaces'] ?> spaces free now</p>

            <a class="btn btn-dark btn-wide" href="find.php?lot=<?= (int) $lot['lot_id'] ?>">Book in <?= h($lot['lot_name']) ?></a>
        </article>
    <?php endforeach; ?>
</div>

<?php require 'inc/bottom.php'; ?>
