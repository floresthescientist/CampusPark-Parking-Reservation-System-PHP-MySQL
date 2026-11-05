<?php
/*
 * my_vehicles.php - Register cars and see them listed
 * INSERT INTO vehicles from the form.
 *
 * JOIN used: vehicles LEFT JOIN bookings
 *   LEFT so a brand-new car with zero bookings still shows up.
 *   COUNT and MAX give "times parked" and "last parked".
 */
require 'inc/connect.php';
require 'inc/helpers.php';

must_login();

$colors   = ['Black', 'White', 'Silver', 'Gray', 'Blue', 'Red', 'Green', 'Other'];
$problems = [];
$plate    = '';
$makeModel = '';
$color    = '';

// How many cars does this member already have?
$count = $db->prepare('SELECT COUNT(*) FROM vehicles WHERE member_id = :me');
$count->execute([':me' => member_id()]);
$carCount = (int) $count->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!form_key_ok()) {
        $problems[] = 'This form expired. Please try again.';
    }

    // Plates are saved in capital letters without spaces or dashes
    $plate     = strtoupper(str_replace([' ', '-'], '', clean('plate_number')));
    $makeModel = clean('make_model');
    $color     = clean('color');

    if (!preg_match('/^[A-Z0-9]{2,8}$/', $plate)) {
        $problems[] = 'License plates are 2 to 8 letters or numbers.';
    }
    if ($makeModel === '' || strlen($makeModel) > 60 || !preg_match("/^[A-Za-z0-9 .'-]+$/", $makeModel)) {
        $problems[] = 'Enter the make and model, like "Toyota Corolla".';
    }
    if (!in_array($color, $colors, true)) {
        $problems[] = 'Choose a color from the list.';
    }
    if ($carCount >= MAX_VEHICLES) {
        $problems[] = 'You can register up to ' . MAX_VEHICLES . ' cars.';
    }

    // Plate numbers must be unique across all members
    if (!$problems) {
        $check = $db->prepare('SELECT vehicle_id FROM vehicles WHERE plate_number = :plate');
        $check->execute([':plate' => $plate]);
        if ($check->fetch()) {
            $problems[] = "The plate $plate is already registered.";
        }
    }

    if (!$problems) {
        $add = $db->prepare('INSERT INTO vehicles (member_id, plate_number, make_model, color)
                             VALUES (:me, :plate, :make_model, :color)');
        $add->execute([
            ':me'         => member_id(),
            ':plate'      => $plate,
            ':make_model' => $makeModel,
            ':color'      => $color,
        ]);
        set_notice('ok', "$plate was added. You can book a space for it now.");
        go('my_vehicles.php');
    }
}

$sql = "SELECT v.vehicle_id, v.plate_number, v.make_model, v.color,
               COUNT(b.booking_id) AS times_parked,
               MAX(b.starts_at)    AS last_parked
        FROM vehicles v
        LEFT JOIN bookings b
               ON b.vehicle_id = v.vehicle_id
              AND b.booking_status = 'Active'
        WHERE v.member_id = :me
        GROUP BY v.vehicle_id, v.plate_number, v.make_model, v.color
        ORDER BY v.plate_number";
$query = $db->prepare($sql);
$query->execute([':me' => member_id()]);
$cars = $query->fetchAll();

$title = 'My Vehicles';
require 'inc/top.php';
?>

<h1 class="page-title">My vehicles</h1>

<div class="split">
    <section>
        <?php if (!$cars): ?>
            <p class="empty-hint">No cars yet. Add one to start booking.</p>
        <?php else: ?>
            <ul class="car-list">
                <?php foreach ($cars as $car): ?>
                    <li class="car-row">
                        <span class="plate plate-big"><?= h($car['plate_number']) ?></span>
                        <span class="car-row-info">
                            <strong><?= h($car['make_model']) ?></strong>
                            <span class="small soft"><?= h($car['color']) ?> &middot;
                                <?php if ($car['times_parked'] > 0): ?>
                                    booked <?= (int) $car['times_parked'] ?> time<?= $car['times_parked'] > 1 ? 's' : '' ?>,
                                    last on <?= h(date('M j', strtotime($car['last_parked']))) ?>
                                <?php else: ?>
                                    no bookings yet
                                <?php endif; ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-dark" href="find.php">Book a space</a>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Add a car</h2>
        <?php if ($carCount >= MAX_VEHICLES): ?>
            <p class="soft">You've reached the limit of <?= MAX_VEHICLES ?> cars.</p>
        <?php else: ?>
            <?php if ($problems): ?>
                <div class="notice notice-bad">
                    <?php foreach ($problems as $problem): ?><p><?= h($problem) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="my_vehicles.php" class="stack-form" data-check>
                <?= form_key_input() ?>
                <div class="field">
                    <label for="plate_number">License plate</label>
                    <input type="text" id="plate_number" name="plate_number" maxlength="10" required
                           value="<?= h($plate) ?>" placeholder="ABC1234" class="plate-input">
                </div>
                <div class="field">
                    <label for="make_model">Make &amp; model</label>
                    <input type="text" id="make_model" name="make_model" maxlength="60" required
                           value="<?= h($makeModel) ?>" placeholder="Toyota Corolla">
                </div>
                <div class="field">
                    <label for="color">Color</label>
                    <select id="color" name="color" required>
                        <option value="">Choose&hellip;</option>
                        <?php foreach ($colors as $option): ?>
                            <option <?= $option === $color ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-yellow btn-wide">Save car</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require 'inc/bottom.php'; ?>
