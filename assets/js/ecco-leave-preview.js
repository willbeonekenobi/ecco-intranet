jQuery(function ($) {

    function calculateDays(start, end) {

        if (!start || !end) return 0;

        let startDate = new Date(start);
        let endDate = new Date(end);

        let diff = endDate - startDate;
        let days = diff / (1000 * 60 * 60 * 24) + 1;

        return days > 0 ? days : 0;
    }

    function updatePreview() {

        let leaveType = $('#leave_type').val();
        let startDate = $('#start_date').val();
        let endDate = $('#end_date').val();

        if (!leaveType) return;

        $.post(ecco_ajax.ajax_url, {
            action: 'ecco_get_leave_balance',
            leave_type: leaveType
        }, function (response) {

            if (!response.success) return;

            let current = parseFloat(response.data.balance);
            let requested = calculateDays(startDate, endDate);
            let remaining = current - requested;

            $('#current_balance').text(current.toFixed(1));
            $('#requested_days').text(requested);
            $('#remaining_balance').text(remaining.toFixed(1));

            if (remaining < 0) {
                $('#remaining_balance').css('color', 'red');
            } else {
                $('#remaining_balance').css('color', '');
            }
        });
    }

    $('#leave_type, #start_date, #end_date').on('change', updatePreview);

});