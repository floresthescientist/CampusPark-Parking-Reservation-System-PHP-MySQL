<?php
/*
 * inc/top.php
 * The top of every page: <head>, the header bar and the menu.
 * Each page sets $title before including this file.
 */

$thisPage = basename($_SERVER['SCRIPT_NAME']);

$menu = [
    'index.php'  => 'Lots',
    'find.php'   => 'Find a Space',
    'report.php' => 'Lot Report',
];
if (member_id()) {
    $menu['my_bookings.php'] = 'My Bookings';
    $menu['my_vehicles.php'] = 'My Vehicles';
}

$notice = take_notice();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'CampusPark') ?> | CampusPark</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/campuspark.css">
</head>
<body>

<header class="topbar">
    <div class="hazard" aria-hidden="true"></div>
    <div class="shell topbar-row">
        <a class="brand" href="index.php">
            <span class="brand-sign" aria-hidden="true">CP</span>
            <span class="brand-words">
                <span class="brand-name">CampusPark</span>
                <span class="brand-sub">Lehman College parking</span>
            </span>
        </a>

        <button class="menu-btn" type="button" aria-controls="site-menu" aria-expanded="false">
            <span class="menu-btn-bars" aria-hidden="true"></span>
            Menu
        </button>

        <nav id="site-menu" class="site-menu" aria-label="Main menu">
            <?php foreach ($menu as $file => $label): ?>
                <a href="<?= $file ?>"<?= $thisPage === $file ? ' class="is-here" aria-current="page"' : '' ?>><?= h($label) ?></a>
            <?php endforeach; ?>

            <span class="menu-account">
                <?php if (member_id()): ?>
                    <span class="hello">Hi, <?= h(member_first_name()) ?></span>
                    <a href="logout.php" class="btn btn-ghost btn-small">Log out</a>
                <?php else: ?>
                    <a href="login.php"<?= $thisPage === 'login.php' ? ' class="is-here"' : '' ?>>Log in</a>
                    <a href="signup.php" class="btn btn-yellow btn-small">Sign up</a>
                <?php endif; ?>
            </span>
        </nav>
    </div>
</header>

<main class="shell page">
    <?php if ($notice): ?>
        <p class="notice notice-<?= h($notice['kind']) ?>" role="status"><?= h($notice['text']) ?></p>
    <?php endif; ?>
