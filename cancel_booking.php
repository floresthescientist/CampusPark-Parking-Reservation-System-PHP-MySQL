<?php
/*
 * cancel_booking.php - Cancels a booking (POST only, no page of its own)
 * Nothing is deleted. One UPDATE with JOINs changes two tables at once:
 *   bookings.booking_status -> 'Cancelled'
 *   payments.payment_status -> 'Refunded'
 * The JOIN to vehicles lets the WHERE clause check that the booking
 * belongs to the logged-in member, and that it hasn't started yet.
 */
require 'inc/connect.php';
require 'inc/helpers.php';

must_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !form_key_ok()) {
    set_notice('bad', 'That request was not allowed.');
    go('my_bookings.php');
}

$bookingId = (int) clean('booking_id');

$sql = "UPDATE bookings b
        INNER JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        INNER JOIN payments p ON p.booking_id = b.booking_id
        SET b.booking_status = 'Cancelled',
            p.payment_status = 'Refunded'
        WHERE b.booking_id = :booking_id
          AND v.member_id = :me
          AND b.booking_status = 'Active'
          AND b.starts_at > :now";
$query = $db->prepare($sql);
$query->execute([
    ':booking_id' => $bookingId,
    ':me'         => member_id(),
    ':now'        => date('Y-m-d H:i:s'),
]);

if ($query->rowCount() > 0) {
    set_notice('ok', "Booking #$bookingId was cancelled and your payment was refunded.");
} else {
    set_notice('bad', 'That booking could not be cancelled. It may have already started or been cancelled.');
}
go('my_bookings.php');
