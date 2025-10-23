//For User Interface
(function (global) {
    'use strict';
    function showFeedback(feedbackSpan, status, message = '') {
        if (!feedbackSpan) return;
        feedbackSpan.textContent = status === 'success' ? '✓' : status === 'error' ? '✗' : '...';
        feedbackSpan.className = 'input-group-text feedback-span';
        feedbackSpan.classList.add(
            status === 'success' ? 'text-success' :
                status === 'error'   ? 'text-danger'  : 'text-muted'
        );
        feedbackSpan.title = message;
    }
    // Enregistrement App + alias global pour rétrocompatibilité
    if (global.App && typeof global.App.register === 'function') {
        (global.App.register('Feedback', { show: showFeedback }));
    }
    global.showFeedback = showFeedback;
})(window);