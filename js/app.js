/*
 * js/app.js - CampusPark front-end
 * These are only conveniences for the user. Every rule is checked
 * again in PHP, so the site still works safely if JavaScript is off.
 */

// Run setup once the page has loaded
document.addEventListener('DOMContentLoaded', function () {
    setupMenu();
    setupEstimate();
    setupCardFields();
    setupPlateField();
    setupPasswordTools();
    setupFormCheck();
    setupCancelButtons();
    setupPrintButton();
});


/* Phone menu: open and close with the Menu button */
function setupMenu() {
    var button = document.querySelector('.menu-btn');
    var menu = document.getElementById('site-menu');
    if (!button || !menu) return;

    button.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('is-open');
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}


/* Find page: show the price and leave time while the user picks options */
function setupEstimate() {
    var lot = document.getElementById('lot');
    var start = document.getElementById('start');
    var hours = document.getElementById('hours');
    var output = document.getElementById('estimate');
    if (!lot || !start || !hours || !output) return;

    function update() {
        var chosenLot = lot.options[lot.selectedIndex];
        var price = parseFloat(chosenLot.getAttribute('data-price'));
        var h = parseInt(hours.value, 10);

        if (!price || !start.value) {
            output.textContent = '';
            return;
        }

        // work out the leave time from the start time + hours
        var parts = start.value.split(':');
        var endMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + h * 60;
        var endHour = Math.floor(endMinutes / 60);
        var endMin = endMinutes % 60;

        var leaveText;
        if (endHour >= 24) {
            leaveText = 'after midnight (too late)';
        } else {
            var suffix = endHour >= 12 ? 'pm' : 'am';
            var hour12 = endHour % 12 === 0 ? 12 : endHour % 12;
            leaveText = hour12 + ':' + (endMin < 10 ? '0' : '') + endMin + ' ' + suffix;
        }

        output.textContent = 'Estimate: ' + h + ' hr x $' + price.toFixed(2) + ' = $' + (h * price).toFixed(2) +
            ' · leave by ' + leaveText;
    }

    lot.addEventListener('change', update);
    start.addEventListener('change', update);
    hours.addEventListener('change', update);
    update();
}


/* Checkout: put spaces in the card number, digits only for CVV */
function setupCardFields() {
    var card = document.getElementById('card_number');
    if (card) {
        card.addEventListener('input', function () {
            var digits = card.value.replace(/[^0-9]/g, '').substring(0, 16);
            var groups = digits.match(/.{1,4}/g);
            card.value = groups ? groups.join(' ') : '';
        });
    }

    var cvv = document.getElementById('cvv');
    if (cvv) {
        cvv.addEventListener('input', function () {
            cvv.value = cvv.value.replace(/[^0-9]/g, '').substring(0, 4);
        });
    }
}


/* Vehicles page: plates are always capital letters */
function setupPlateField() {
    var plate = document.getElementById('plate_number');
    if (!plate) return;

    plate.addEventListener('input', function () {
        plate.value = plate.value.toUpperCase().replace(/[^A-Z0-9 -]/g, '');
    });
}


/* Show/Hide password buttons + live password hint on sign up */
function setupPasswordTools() {
    var buttons = document.querySelectorAll('.show-pass');
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-target'));
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            button.textContent = hidden ? 'Hide' : 'Show';
        });
    });

    var hint = document.getElementById('password-hint');
    var password = document.getElementById('password');
    if (hint && password) {
        password.addEventListener('input', function () {
            var value = password.value;
            var good = value.length >= 8 && /[0-9]/.test(value) && /[A-Za-z]/.test(value);
            hint.textContent = good ? 'Strong enough ✓' : '8+ characters with a letter and a number.';
            hint.classList.toggle('good', good);
        });
    }
}


/* Before a form is sent, outline any empty required fields in red */
function setupFormCheck() {
    var forms = document.querySelectorAll('form[data-check]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var firstBad = null;
            var fields = form.querySelectorAll('input[required]:not([type=radio]), select[required]');

            fields.forEach(function (field) {
                var bad = field.value.trim() === '' || !field.checkValidity();
                field.classList.toggle('is-bad', bad);
                if (bad && !firstBad) firstBad = field;
            });

            // radio buttons: at least one in the group must be picked
            var radios = form.querySelectorAll('input[type=radio][required]');
            if (radios.length > 0 && !form.querySelector('input[type=radio]:checked')) {
                if (!firstBad) firstBad = radios[0];
                alert('Please choose a car.');
            }

            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
            }
        });

        // clear the red outline as soon as the user fixes the field
        form.addEventListener('input', function (event) {
            event.target.classList.remove('is-bad');
        });
    });
}


/* My bookings: ask before cancelling */
function setupCancelButtons() {
    var forms = document.querySelectorAll('form.js-cancel');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var sure = confirm('Cancel this booking? You will get a full refund.');
            if (!sure) event.preventDefault();
        });
    });
}


/* Parking pass: Print button */
function setupPrintButton() {
    var button = document.querySelector('[data-print]');
    if (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    }
}
