/**
 * Aptitude Test Management System — progressive enhancements.
 *
 * Everything here is optional polish: the application is fully usable
 * without JavaScript.
 */
(function () {
    'use strict';

    // Auto-dismiss flash messages after a short delay.
    document.querySelectorAll('.flash').forEach(function (flash) {
        window.setTimeout(function () {
            flash.style.transition = 'opacity .4s ease, transform .4s ease';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-6px)';
            window.setTimeout(function () { flash.remove(); }, 420);
        }, 6000);
    });

    // Close the mobile navigation after a link is followed.
    document.querySelectorAll('.site-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            var toggle = document.getElementById('nav-toggle');
            if (toggle) { toggle.checked = false; }
        });
    });

    // Guard against double submissions and show progress on long forms.
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submit && !submit.disabled) {
                submit.dataset.label = submit.textContent;
                submit.textContent = 'Please wait…';
                window.setTimeout(function () { submit.disabled = true; }, 0);
            }
        });
    });

    // Animate quiz progress bars on load.
    document.querySelectorAll('.progress__bar[data-value]').forEach(function (bar) {
        var value = Math.max(0, Math.min(100, parseFloat(bar.dataset.value) || 0));
        bar.style.width = '0%';
        window.requestAnimationFrame(function () { bar.style.width = value + '%'; });
    });

    // Copy the registration link to the clipboard.
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.querySelector(button.dataset.copyTarget);
            if (!field) { return; }
            var original = button.textContent;

            function done(label) {
                button.textContent = label;
                window.setTimeout(function () { button.textContent = original; }, 1800);
            }

            function fallback() {
                field.removeAttribute('readonly');
                field.select();
                try { document.execCommand('copy'); done('Copied!'); }
                catch (error) { done('Press Ctrl+C'); }
                field.setAttribute('readonly', 'readonly');
                field.blur();
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(field.value).then(function () {
                    done('Copied!');
                }, fallback);
            } else {
                fallback();
            }
        });
    });

    // Offer the platform share sheet when the browser supports it.
    document.querySelectorAll('[data-share-url]').forEach(function (button) {
        if (!navigator.share) { return; }
        button.hidden = false;
        button.addEventListener('click', function () {
            navigator.share({
                title: button.dataset.shareTitle,
                text: button.dataset.shareText,
                url: button.dataset.shareUrl
            }).catch(function () { /* dismissed */ });
        });
    });

    // Warn before leaving an in-progress quiz.
    var quiz = document.querySelector('[data-quiz-in-progress]');
    if (quiz) {
        window.addEventListener('beforeunload', function (event) {
            event.preventDefault();
            event.returnValue = '';
        });
        quiz.addEventListener('submit', function () {
            window.onbeforeunload = null;
        });
    }
})();
