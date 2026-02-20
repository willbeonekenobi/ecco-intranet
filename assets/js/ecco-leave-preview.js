jQuery(function ($) {

    const $type = $('#ecco_leave_type');
    const $start = $('#ecco_start_date');
    const $end = $('#ecco_end_date');
    const $preview = $('#ecco_balance_preview');

    if (!$type.length || !$start.length || !$end.length || !$preview.length) {
        return;
    }

    function showPreview(text) {
        $preview.text(text);
    }

    function updatePreview() {

        const type = $type.val();
        const startDate = $start.val();
        const endDate = $end.val();

        if (!type || !startDate || !endDate) {
            showPreview('');
            return;
        }

        const balance = parseFloat(
            $type.find(':selected').data('balance') || 0
        );

        showPreview('Calculating…');

        /* ---------------------------------------------------------
           ASK SERVER TO CALCULATE WORKING DAYS
           (EXCLUDES weekends + public holidays)
        --------------------------------------------------------- */

        $.post(eccoLeavePreview.ajaxUrl, {
            action: 'ecco_calculate_leave_days_ajax',
            start_date: startDate,
            end_date: endDate
        })
        .done(function (response) {

            if (!response.success) {
                showPreview('Unable to calculate days');
                return;
            }

            const days = parseFloat(response.data.days) || 0;
            const remaining = balance - days;

            let text =
                'Requested: ' + days +
                ' | Balance: ' + balance +
                ' | After: ' + remaining;

            if (remaining < 0) {
                text += ' ⚠ Not enough leave';
            }

            showPreview(text);
        })
        .fail(function () {
            showPreview('Error calculating days');
        });
    }

    $type.on('change', updatePreview);
    $start.on('change', updatePreview);
    $end.on('change', updatePreview);
});