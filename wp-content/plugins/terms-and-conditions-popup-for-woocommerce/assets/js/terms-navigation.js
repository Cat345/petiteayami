(function ($) {
    'use strict';

    $(document).on('br_popup-after_add_content', function (event, source) {
        var popup = $(source).data('br_popup_object');
        var inner = popup && popup.find('.br_popup_inner')[0];
        var navigation = inner && inner.querySelector('.br_terms_navigation');
        if (!navigation) {
            return;
        }

        // Keep the existing scroll container and the popup's consent controls.
        var panel = document.createElement('div');
        panel.className = 'br_terms_navigation_panel';
        inner.parentNode.insertBefore(panel, inner);
        panel.appendChild(navigation);
        panel.appendChild(inner);
        var progress = navigation.querySelector('.br_terms_scroll_progress');
        var fill = progress.querySelector('.br_terms_scroll_progress_fill');
        var frame;
        var resizeObserver;
        var mutationObserver;

        function update() {
            frame = null;
            var range = inner.scrollHeight - inner.clientHeight;
            var percent = range > 0 ? Math.max(0, Math.min(100, inner.scrollTop / range * 100)) : 100;
            var rounded = Math.round(percent);
            fill.style.width = percent + '%';
            progress.setAttribute('aria-valuenow', rounded);
            progress.setAttribute('aria-valuetext', brTermsNavigationSettings.scrollProgressText.replace('%s', rounded));
        }

        function scheduleUpdate() {
            if (frame == null) {
                frame = window.requestAnimationFrame(update);
            }
        }

        function observeContent() {
            if (resizeObserver) {
                resizeObserver.disconnect();
                resizeObserver.observe(navigation);
                resizeObserver.observe(inner);
                Array.prototype.forEach.call(inner.children, function (child) {
                    resizeObserver.observe(child);
                });
            }
            scheduleUpdate();
        }

        inner.addEventListener('scroll', scheduleUpdate, { passive: true });
        inner.addEventListener('load', scheduleUpdate, true);
        window.addEventListener('resize', scheduleUpdate);
        if (window.ResizeObserver) {
            resizeObserver = new ResizeObserver(scheduleUpdate);
        }
        if (window.MutationObserver) {
            mutationObserver = new MutationObserver(observeContent);
            mutationObserver.observe(inner, { childList: true, subtree: true, characterData: true, attributes: true });
        }
        observeContent();

        $(source).one('br_popup-hide_popup', function () {
            inner.removeEventListener('scroll', scheduleUpdate);
            inner.removeEventListener('load', scheduleUpdate, true);
            window.removeEventListener('resize', scheduleUpdate);
            if (resizeObserver) {
                resizeObserver.disconnect();
            }
            if (mutationObserver) {
                mutationObserver.disconnect();
            }
            window.cancelAnimationFrame(frame);
        });
    });
})(jQuery);
