(function($) {
    $(document).ready(function() {
        if (typeof stackboostUpsellConfig === 'undefined') {
            return;
        }

        var pool = stackboostUpsellConfig.pool;
        var currentIndex = parseInt(stackboostUpsellConfig.startIndex);
        var $widget = $('#stackboost-spotlight-widget');
        var timer = null;
        var intervalTime = 60000; // 60 seconds

        function renderCard(index) {
            if (index < 0) index = pool.length - 1;
            if (index >= pool.length) index = 0;
            currentIndex = index;

            var card = pool[currentIndex];

            // Update content
            $('#sb-spot-icon').attr('class', 'dashicons ' + card.icon);
            $('#sb-spot-title').text(card.hook);
            $('#sb-spot-copy').text(card.copy);
            $('#sb-spot-link').attr('href', card.url);

            // Update badge
            var badgeText = '';
            var badgeClass = 'stackboost-spotlight-badge';

            if (card.pool === 'business') {
                badgeText = stackboostUpsellConfig.i18n.business;
                badgeClass += ' business';
            } else {
                badgeText = stackboostUpsellConfig.i18n.pro;
                badgeClass += ' pro';
            }

            $('#sb-spot-badge').text(badgeText).attr('class', badgeClass);

            // Update border class (remove old, add new)
            $widget.removeClass('stackboost-upsell-pro stackboost-upsell-biz').addClass(card.class);

            $widget.show();
        }

        function nextCard() {
            renderCard(currentIndex + 1);
        }

        function prevCard() {
            renderCard(currentIndex - 1);
        }

        function startTimer() {
            if (timer) clearInterval(timer);
            timer = setInterval(nextCard, intervalTime);
        }

        function stopTimer() {
            if (timer) clearInterval(timer);
        }

        // Controls
        $widget.find('.next').on('click', function() {
            nextCard();
            startTimer(); // Reset timer on interaction
        });

        $widget.find('.prev').on('click', function() {
            prevCard();
            startTimer();
        });

        // Hover pause
        $widget.on('mouseenter', stopTimer).on('mouseleave', startTimer);

        // Init
        if (pool.length > 0) {
            renderCard(currentIndex);
            startTimer();
        }
    });
})(jQuery);
