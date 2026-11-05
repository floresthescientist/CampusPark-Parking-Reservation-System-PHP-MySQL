<?php
/*
 * checkout.php - Pick your car and pay
 * The booking is only saved once the payment form is valid.
 * The booking and the payment are saved together inside one
 * database transaction, so you can never end up with a booking
 * that has no payment (or a payment with no booking).
 *
 * JOIN used: spaces INNER JOIN lots (space details + the lot's price)
 */
require 'inc/connect.php';
require 'inc/helpers.php';

must_login();

$spaceId  = (int) ($_GET['space'] ?? 0);
$startsAt = is_string($_GET['start'] ?? null) ? $_GET['start'] : '';
$hours    = (int) ($_GET['hours'] ?? 0);

// ---- Load the space and its lot ----
$query = $db->prepare('SELECT s.space_id, s.space_code, s.space_type,
                              l.lot_id, l.lot_name, l.location_note, l.price_per_hour, l.opens_at, l.closes_at
                       FROM spaces s
                       INNER JOIN lots l ON l.lot_id = s.lot_id
                       WHERE s.space_id = :space_id');
$query->execute([':space_id' => $spaceId]);
$space = $query->fetch();

if (!$space) {
    set_notice('bad', 'That parking space could not be found.');
    go('find.php');
}

$timeProblems = window_problems($space, $startsAt, $hours);
if ($timeProblems) {
    set_notice('bad', $timeProblems[0]);
    go('find.php?lot=' . $space['lot_id']);
}

$startSql = date('Y-m-d H:i:s', strtotime($startsAt));
$endSql   = date('Y-m-d H:i:s', strtotime($startsAt) + $hours * 3600);
$total    = round($hours * $space['price_per_hour'], 2);

// ---- This member's cars ----
$query = $db->prepare('SELECT vehicle_id, plate_number, make_model FROM vehicles
                       WHERE member_id = :me ORDER BY plate_number');
$query->execute([':me' => member_id()]);
$myCars = $query->fetchAll();

$problems   = [];
$vehicleId  = count($myCars) === 1 ? (int) $myCars[0]['vehicle_id'] : 0;
$cardName   = '';
$thisYear   = (int) date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!form_key_ok()) {
        $problems[] = 'This form expired. Please try again.';
    }

    $vehicleId  = (int) clean('vehicle_id');
    $cardName   = clean('card_name');
    $cardNumber = str_replace([' ', '-'], '', clean('card_number'));
    $expMonth   = (int) clean('exp_month');
    $expYear    = (int) clean('exp_year');
    $cvv        = clean('cvv');

    // The car has to belong to the person who is logged in
    $carIsMine = false;
    foreach ($myCars as $car) {
        if ((int) $car['vehicle_id'] === $vehicleId) {
            $carIsMine = true;
        }
    }
    if (!$carIsMine) {
        $problems[] = 'Choose which of your cars will park here.';
    }

    if ($cardName === '' || strlen($cardName) > 100 || !preg_match("/^[A-Za-z .'-]+$/", $cardName)) {
        $problems[] = 'Enter the name exactly as it appears on the card.';
    }
    if (!preg_match('/^\d{15,16}$/', $cardNumber)) {
        $problems[] = 'Card numbers are 15 or 16 digits.';
    }
    if ($expMonth < 1 || $expMonth > 12 || $expYear < $thisYear || $expYear > $thisYear + 10) {
        $problems[] = 'Choose the card expiration month and year.';
    } elseif ($expYear === $thisYear && $expMonth < (int) date('n')) {
        $problems[] = 'This card is expired.';
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        $problems[] = 'The security code is the 3 or 4 digits on the card.';
    }

    if (!$problems) {
        try {
            $db->beginTransaction();

            // Lock this space's row until we finish, so two people
            // pressing "Pay" at the same moment can't both get it
            $lock = $db->prepare('SELECT space_id FROM spaces WHERE space_id = :id FOR UPDATE');
            $lock->execute([':id' => $spaceId]);

            // Is the space still free? (someone may have booked it while this page was open)
            $check = $db->prepare("SELECT COUNT(*) FROM bookings
                                   WHERE space_id = :id
                                     AND booking_status = 'Active'
                                     AND starts_at < :ends_at
                                     AND ends_at   > :starts_at");
            $check->execute([':id' => $spaceId, ':ends_at' => $endSql, ':starts_at' => $startSql]);

            if ($check->fetchColumn() > 0) {
                $db->rollBack();
                set_notice('warn', 'Sorry, space ' . $space['space_code'] . ' was just taken. Please pick another one.');
                go('find.php?' . http_build_query([
                    'lot' => $space['lot_id'], 'day' => substr($startsAt, 0, 10),
                    'start' => substr($startsAt, 11), 'hours' => $hours,
                ]));
            }

            $save = $db->prepare('INSERT INTO bookings (space_id, vehicle_id, starts_at, ends_at)
                                  VALUES (:space_id, :vehicle_id, :starts_at, :ends_at)');
            $save->execute([
                ':space_id'   => $spaceId,
                ':vehicle_id' => $vehicleId,
                ':starts_at'  => $startSql,
                ':ends_at'    => $endSql,
            ]);
            $bookingId = (int) $db->lastInsertId();

            // Only the last 4 digits are kept. The full number and CVV are thrown away.
            $pay = $db->prepare('INSERT INTO payments (booking_id, amount_paid, card_name, card_last4)
                                 VALUES (:booking_id, :amount, :card_name, :last4)');
            $pay->execute([
                ':booking_id' => $bookingId,
                ':amount'     => $total,
                ':card_name'  => $cardName,
                ':last4'      => substr($cardNumber, -4),
            ]);

            $db->commit();
            set_notice('ok', 'Payment approved. Here is your parking pass!');
            go('receipt.php?b=' . $bookingId);

        } catch (PDOException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('CampusPark checkout error: ' . $error->getMessage());
            $problems[] = 'Something went wrong while saving your booking. Nothing was charged. Please try again.';
        }
    }
}

$title = 'Checkout';
require 'inc/top.php';
?>

<h1 class="page-title">Checkout</h1>

<div class="checkout">
    <aside class="ticket">
        <p class="ticket-label">Your space</p>
        <p class="ticket-code"><?= h($space['space_code']) ?></p>
        <p class="ticket-lot"><?= h($space['lot_name']) ?></p>
        <p class="ticket-where"><?= h($space['location_note']) ?></p>

        <div class="ticket-tear" aria-hidden="true"></div>

        <dl class="ticket-rows">
            <div><dt>Type</dt><dd><?= h($space['space_type']) ?></dd></div>
            <div><dt>Date</dt><dd><?= h(show_date($startSql)) ?></dd></div>
            <div><dt>Time</dt><dd><?= h(show_time($startSql)) ?> &ndash; <?= h(show_time($endSql)) ?></dd></div>
            <div><dt>Rate</dt><dd><?= $hours ?> hr &times; <?= money($space['price_per_hour']) ?></dd></div>
            <div class="ticket-total"><dt>Total</dt><dd><?= money($total) ?></dd></div>
        </dl>
        <a href="find.php?<?= h(http_build_query(['lot' => $space['lot_id'], 'day' => substr($startsAt, 0, 10), 'start' => substr($startsAt, 11), 'hours' => $hours])) ?>" class="ticket-back">&larr; Pick a different space</a>
    </aside>

    <section class="panel">
        <?php if ($problems): ?>
            <div class="notice notice-bad">
                <?php foreach ($problems as $problem): ?><p><?= h($problem) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$myCars): ?>
            <h2>Add a car first</h2>
            <p>Bookings are tied to a license plate so campus security knows the car is allowed to be there.</p>
            <a class="btn btn-dark" href="my_vehicles.php">Add my car</a>
        <?php else: ?>
            <form method="post" action="checkout.php?<?= h(http_build_query(['space' => $spaceId, 'start' => $startsAt, 'hours' => $hours])) ?>" class="stack-form" autocomplete="off" data-check>
                <?= form_key_input() ?>

                <fieldset>
                    <legend>1. Which car?</legend>
                    <div class="car-choices">
                        <?php foreach ($myCars as $car): ?>
                            <label class="car-choice">
                                <input type="radio" name="vehicle_id" value="<?= (int) $car['vehicle_id'] ?>" required
                                    <?= (int) $car['vehicle_id'] === $vehicleId ? 'checked' : '' ?>>
                                <span><b class="plate"><?= h($car['plate_number']) ?></b> <?= h($car['make_model']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>2. Payment</legend>
                    <p class="soft small">Class project: no real charge. Try the test card <code>4242 4242 4242 4242</code>.</p>

                    <div class="field">
                        <label for="card_name">Name on card</label>
                        <input type="text" id="card_name" name="card_name" maxlength="100" required value="<?= h($cardName) ?>">
                    </div>
                    <div class="field">
                        <label for="card_number">Card number</label>
                        <input type="text" id="card_number" name="card_number" inputmode="numeric" maxlength="19" required placeholder="0000 0000 0000 0000">
                    </div>
                    <div class="field-trio">
                        <div class="field">
                            <label for="exp_month">Exp. month</label>
                            <select id="exp_month" name="exp_month" required>
                                <option value="">MM</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>"><?= sprintf('%02d', $m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="exp_year">Exp. year</label>
                            <select id="exp_year" name="exp_year" required>
                                <option value="">YYYY</option>
                                <?php for ($y = $thisYear; $y <= $thisYear + 10; $y++): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="cvv">CVV</label>
                            <input type="password" id="cvv" name="cvv" inputmode="numeric" maxlength="4" required>
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-yellow btn-wide">Pay <?= money($total) ?> &amp; book <?= h($space['space_code']) ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require 'inc/bottom.php'; ?>
