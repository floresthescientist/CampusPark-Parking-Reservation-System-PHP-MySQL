<?php
/*
 * inc/helpers.php
 * Small functions every page uses: safe output, messages,
 * form keys (CSRF), login checks, formatting and validation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use New York time so "today" matches the campus clock
date_default_timezone_set('America/New_York');

define('MAX_HOURS', 8);      // longest booking allowed
define('DAYS_AHEAD', 14);    // how far in the future you can book
define('MAX_VEHICLES', 3);   // cars per member


/* ---------- Output ---------- */

// Makes text safe to print inside HTML (stops XSS attacks)
function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Sends the browser to another page and stops this one
function go($page)
{
    header('Location: ' . $page);
    exit;
}

// A message that shows up once on the next page (kind = ok, warn or bad)
function set_notice($kind, $text)
{
    $_SESSION['notice'] = ['kind' => $kind, 'text' => $text];
}

function take_notice()
{
    if (!isset($_SESSION['notice'])) {
        return null;
    }
    $notice = $_SESSION['notice'];
    unset($_SESSION['notice']);
    return $notice;
}


/* ---------- Form keys (CSRF protection) ----------
   Every POST form includes a secret key stored in the session.
   If a form arrives without the right key, it did not come from our site. */

function form_key()
{
    if (empty($_SESSION['form_key'])) {
        $_SESSION['form_key'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['form_key'];
}

function form_key_input()
{
    return '<input type="hidden" name="form_key" value="' . h(form_key()) . '">';
}

function form_key_ok()
{
    $sent = $_POST['form_key'] ?? '';
    return !empty($_SESSION['form_key']) && is_string($sent) && hash_equals($_SESSION['form_key'], $sent);
}


/* ---------- Login ---------- */

function member_id()
{
    return $_SESSION['member_id'] ?? null;
}

function member_first_name()
{
    return $_SESSION['first_name'] ?? '';
}

// Put this at the top of pages that need an account
function must_login()
{
    if (!member_id()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        set_notice('warn', 'Please log in to continue.');
        go('login.php');
    }
}


/* ---------- Reading input ---------- */

// Trimmed value from a POST form ('' if missing)
function clean($field)
{
    $value = $_POST[$field] ?? '';
    return is_string($value) ? trim($value) : '';
}


/* ---------- Formatting ---------- */

function money($amount)
{
    return '$' . number_format((float) $amount, 2);
}

function show_date($dateTime)
{
    return date('D, M j, Y', strtotime($dateTime));
}

function show_time($dateTime)
{
    return date('g:i a', strtotime($dateTime));
}

// Start times shown in the dropdowns: every 30 minutes from 5:00 am to 11:30 pm
function start_time_choices()
{
    $choices = [];
    for ($minutes = 5 * 60; $minutes <= 23 * 60 + 30; $minutes += 30) {
        $choices[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
    return $choices;
}


/* ---------- Validation ---------- */

/*
 * Checks a booking window against a lot's rules.
 *   $lot      = row from the lots table
 *   $startsAt = 'YYYY-MM-DD HH:MM'
 *   $hours    = whole number of hours
 * Returns an array of problems. An empty array means everything is OK.
 */
function window_problems($lot, $startsAt, $hours)
{
    $start = DateTime::createFromFormat('!Y-m-d H:i', $startsAt);
    if (!$start || $start->format('Y-m-d H:i') !== $startsAt) {
        return ['Please choose a valid date and start time.'];
    }
    if ($hours < 1 || $hours > MAX_HOURS) {
        return ['You can book from 1 to ' . MAX_HOURS . ' hours.'];
    }

    $problems = [];
    $end = (clone $start)->modify("+$hours hours");
    $lastDay = date('Y-m-d', strtotime('+' . DAYS_AHEAD . ' days'));

    if ((int) $start->format('i') % 30 !== 0) {
        $problems[] = 'Start times have to be on the hour or half hour.';
    }
    if ($start <= new DateTime()) {
        $problems[] = 'That start time has already passed. Pick a later time.';
    } elseif ($start->format('Y-m-d') > $lastDay) {
        $problems[] = 'You can only book up to ' . DAYS_AHEAD . ' days ahead.';
    }

    // The whole stay must fit inside the lot's opening hours on that day
    $day    = $start->format('Y-m-d');
    $opens  = "$day {$lot['opens_at']}";
    $closes = "$day {$lot['closes_at']}";
    if ($start->format('Y-m-d H:i:s') < $opens || $end->format('Y-m-d H:i:s') > $closes) {
        $problems[] = $lot['lot_name'] . ' is open ' . show_time($lot['opens_at']) . ' to '
            . show_time($lot['closes_at']) . '. Your whole stay has to fit inside those hours.';
    }

    return $problems;
}
