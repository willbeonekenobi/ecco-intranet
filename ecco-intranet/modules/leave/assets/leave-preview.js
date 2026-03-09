document.addEventListener('DOMContentLoaded', function () {

    function daysBetween(start, end) {
        if (!start || !end) return 0;

        const s = new Date(start);
        const e = new Date(end);

        const diff = e - s;
        const days = diff / (1000 * 60 * 60 * 24) + 1;

        return days > 0 ? days : 0;
    }

    function updatePreview() {

        const leaveType = document.getElementById('ecco_leave_type').value;
        const start = document.getElementById('ecco_start_date').value;
        const end = document.getElementById('ecco_end_date').value;

        if (!leaveType) return;

        fetch(eccoLeave.ajax_url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'ecco_get_leave_balance',
                leave_type: leaveType
            })
        })
        .then(r => r.json())
        .then(data => {

            if (!data.success) return;

            const current = parseFloat(data.data.balance);
            const requested = daysBetween(start, end);
            const remaining = current - requested;

            document.getElementById('ecco_current_balance').textContent = current.toFixed(1);
            document.getElementById('ecco_requested_days').textContent = requested;
            document.getElementById('ecco_remaining_balance').textContent = remaining.toFixed(1);

            if (remaining < 0) {
                document.getElementById('ecco_remaining_balance').style.color = 'red';
            } else {
                document.getElementById('ecco_remaining_balance').style.color = '';
            }
        });
    }

    document.getElementById('ecco_leave_type').addEventListener('change', updatePreview);
    document.getElementById('ecco_start_date').addEventListener('change', updatePreview);
    document.getElementById('ecco_end_date').addEventListener('change', updatePreview);
});