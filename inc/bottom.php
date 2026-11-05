<?php
/*
 * inc/bottom.php
 * Closes the page: footer + JavaScript file.
 */
?>
</main>

<footer class="footer">
    <div class="shell footer-cols">
        <div>
            <p class="footer-brand">CampusPark</p>
            <p>Book a parking space on campus before you leave home. No more circling the lot before class.</p>
        </div>
        <div>
            <p class="footer-head">Get around</p>
            <ul class="footer-links">
                <li><a href="find.php">Find a space</a></li>
                <li><a href="my_bookings.php">My bookings</a></li>
                <li><a href="report.php">Lot report</a></li>
            </ul>
        </div>
        <div>
            <p class="footer-head">Need help?</p>
            <p>Lot hours and prices are listed on the <a href="index.php">Lots</a> page. You can cancel any booking before it starts for a full refund.</p>
        </div>
    </div>
    <div class="shell footer-base">
        <span>CIS 344 Group Project &middot; Fall <?= date('Y') ?></span>
        <span>Lehman College, CUNY</span>
    </div>
</footer>

<script src="js/app.js"></script>
</body>
</html>
