<?php
/*
 * find.php - Search for a free space
 * The visitor picks a lot, a day, a start time and how many hours.
 * We then draw the lot as a row of stalls: free stalls can be clicked,
 * taken stalls are greyed out.
 *
 * JOIN used:
 *   spaces LEFT JOIN bookings that OVERLAP the chosen window.
 *   COUNT(b.booking_id) = 0 means nobody has that space at that time.
 *   Two time windows overlap when:  old.start < new.end  AND  old.end > new.start
 */
require 'inc/connect.php';
require 'inc/helpers.php';

$lots = $db->query('SELECT lot_id, lot_name, price_per_hour, opens_at, closes_at
                    FROM lots ORDER BY lot_name')->fetchAll();

// Read the search form (it uses GET so the results page can be bookmarked)
$lotId     = (int) ($_GET['lot'] ?? 0);
$day       = $_GET['day'] ?? date('Y-m-d');
$startTime = $_GET['start'] ?? '';
$hours     = (int) ($_GET['hours'] ?? 2);
$searched  = isset($_GET['start']);

if (!is_string($day)) $day = date('Y-m-d');
if (!is_string($startTime)) $startTime = '';

// Find the chosen lot in the list we already loaded
$lot = null;
foreach ($lots as $row) {
    if ((int) $row['lot_id'] === $lotId) {
        $lot = $row;
    }
}

$problems = [];
$stalls   = [];
$freeCount = 0;

if ($searched) {
    if (!$lot) {
        $problems[] = 'Please choose a parking lot.';
    } else {
        $problems = window_problems($lot, "$day $startTime", $hours);
    }

    if (!$problems) {
        $startsAt = date('Y-m-d H:i:s', strtotime("$day $startTime"));
        $endsAt   = date('Y-m-d H:i:s', strtotime("$day $startTime") + $hours * 3600);

        $sql = "SELECT s.space_id, s.space_code, s.space_type,
                       COUNT(b.booking_id) AS clashes
                FROM spaces s
                LEFT JOIN bookings b
                       ON b.space_id = s.space_id
                      AND b.booking_status = 'Active'
                      AND b.starts_at < :ends_at
                      AND b.ends_at   > :starts_at
                WHERE s.lot_id = :lot_id
                GROUP BY s.space_id, s.space_code, s.space_type
                ORDER BY s.space_code";
        $query = $db->prepare($sql);
        $query->execute([':ends_at' => $endsAt, ':starts_at' => $startsAt, ':lot_id' => $lotId]);
        $stalls = $query->fetchAll();

        foreach ($stalls as $stall) {
            if ($stall['clashes'] == 0) {
                $freeCount++;
            }
        }
    }
}

// Little symbols shown on each stall
$typeIcons = ['Standard' => '', 'Compact' => 'C', 'Accessible' => '♿', 'EV Charging' => '⚡'];

$title = 'Find a Space';
require 'inc/top.php';
?>

<h1 class="page-title">Find a space</h1>

<form method="get" action="find.php" class="search-bar" id="search-form">
    <div class="field">
        <label for="lot">Lot</label>
        <select id="lot" name="lot" required>
            <option value="">Choose a lot&hellip;</option>
            <?php foreach ($lots as $row): ?>
                <option value="<?= (int) $row['lot_id'] ?>" data-price="<?= h($row['price_per_hour']) ?>"
                    <?= (int) $row['lot_id'] === $lotId ? 'selected' : '' ?>>
                    <?= h($row['lot_name']) ?> (<?= money($row['price_per_hour']) ?>/hr)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="day">Day</label>
        <input type="date" id="day" name="day" required value="<?= h($day) ?>"
               min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+' . DAYS_AHEAD . ' days')) ?>">
    </div>
    <div class="field">
        <label for="start">Arrive at</label>
        <select id="start" name="start" required>
            <option value="">Time&hellip;</option>
            <?php foreach (start_time_choices() as $time): ?>
                <option value="<?= $time ?>" <?= $time === $startTime ? 'selected' : '' ?>><?= h(show_time($time)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="hours">Stay for</label>
        <select id="hours" name="hours">
            <?php for ($i = 1; $i <= MAX_HOURS; $i++): ?>
                <option value="<?= $i ?>" <?= $i === $hours ? 'selected' : '' ?>><?= $i ?> hour<?= $i > 1 ? 's' : '' ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-dark">Show spaces</button>
    <p class="estimate" id="estimate" aria-live="polite"></p>
</form>

<?php if ($problems): ?>
    <div class="notice notice-bad">
        <?php foreach ($problems as $problem): ?><p><?= h($problem) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($searched && !$problems): ?>
    <section class="results">
        <div class="results-head">
            <div>
                <h2><?= h($lot['lot_name']) ?></h2>
                <p class="soft">
                    <?= h(show_date($startsAt)) ?>, <?= h(show_time($startsAt)) ?> &ndash; <?= h(show_time($endsAt)) ?>
                    &middot; <?= $hours ?> hr &times; <?= money($lot['price_per_hour']) ?> =
                    <strong><?= money($hours * $lot['price_per_hour']) ?></strong>
                </p>
            </div>
            <p class="free-count"><strong><?= $freeCount ?></strong> of <?= count($stalls) ?> open</p>
        </div>

        <?php if ($freeCount === 0): ?>
            <p class="notice notice-warn">Every space in this lot is taken for that time. Try another lot or a different time.</p>
        <?php endif; ?>

        <div class="asphalt">
            <div class="stalls">
                <?php foreach ($stalls as $stall):
                    $isFree = $stall['clashes'] == 0;
                    $icon   = $typeIcons[$stall['space_type']] ?? '';
                ?>
                    <?php if ($isFree): ?>
                        <a class="stall stall-free"
                           href="checkout.php?<?= h(http_build_query(['space' => $stall['space_id'], 'start' => "$day $startTime", 'hours' => $hours])) ?>"
                           title="<?= h($stall['space_type']) ?> space - click to book">
                            <span class="stall-icon"><?= $icon ?></span>
                            <span class="stall-code"><?= h($stall['space_code']) ?></span>
                            <span class="stall-state">Book</span>
                        </a>
                    <?php else: ?>
                        <div class="stall stall-taken" title="Already booked for this time">
                            <span class="stall-icon"><?= $icon ?></span>
                            <span class="stall-code"><?= h($stall['space_code']) ?></span>
                            <span class="stall-state">Taken</span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <p class="drive-lane" aria-hidden="true">&larr; DRIVE LANE &rarr;</p>
        </div>

        <ul class="key">
            <li><span class="key-box key-free"></span>Open</li>
            <li><span class="key-box key-taken"></span>Taken</li>
            <li>♿ Accessible</li>
            <li>⚡ EV charging</li>
            <li>C Compact</li>
        </ul>

        <?php if (!member_id()): ?>
            <p class="soft">You'll be asked to <a href="login.php">log in</a> or <a href="signup.php">sign up</a> when you pick a space.</p>
        <?php endif; ?>
    </section>
<?php elseif (!$searched): ?>
    <p class="empty-hint">Choose a lot and a time above to see which spaces are open.</p>
<?php endif; ?>

<?php require 'inc/bottom.php'; ?>
