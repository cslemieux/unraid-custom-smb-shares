/**
 * Show notification message
 * @param {string} message - Message to display
 * @param {string} type - Notification type ('success' or 'error')
 */
function showNotification(message, type)
{
    var notification = $('<div class="notification notification-' + type + '"></div>');
    notification.text(message);
    $('body').append(notification);

    setTimeout(function () {
        notification.addClass('show');
    }, 10);

    setTimeout(function () {
        notification.removeClass('show');
        setTimeout(function () {
            notification.remove();
        }, 300);
    }, 3000);
}

/**
 * Show success notification
 * @param {string} message - Success message
 */
function showSuccess(message)
{
    showNotification(message, 'success');
}

/**
 * Show error notification
 * @param {string} message - Error message
 */
function showError(message)
{
    showNotification(message, 'error');
}
