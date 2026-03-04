jQuery(document).ready(function ($) {

    console.log('ECCO Calendar JS Loaded');

    const select = $('#ecco-group-selector');
    const calendarEl = document.getElementById('ecco-calendar');

    if (!select.length || !calendarEl) {
        console.error('Calendar elements missing');
        return;
    }

    // 🔹 Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        events: [] // empty initially
    });

    calendar.render();

    // 🔹 Load Groups
    $.ajax({
        url: eccoCalendar.ajax_url,
        method: 'POST',
        data: {
            action: 'ecco_get_groups'
        },
        success: function (response) {

            console.log('AJAX Response:', response);

            if (!response.success) {
                select.html('<option value="">Failed to load groups</option>');
                return;
            }

            select.empty();
            select.append('<option value="">Select Group</option>');

            response.data.forEach(function (group) {
                select.append(
                    $('<option>', {
                        value: group.id,
                        text: group.title
                    })
                );
            });

        }
    });

    // 🔹 When Group Changes → Load Events
    select.on('change', function () {

        const groupId = $(this).val();

        if (!groupId) return;

        console.log('Loading events for group:', groupId);

        $.ajax({
            url: eccoCalendar.ajax_url,
            method: 'POST',
            data: {
                action: 'ecco_get_group_events',
                group_id: groupId
            },
            success: function (response) {

                if (!response.success) {
                    alert('Could not load events');
                    return;
                }

                calendar.removeAllEvents();
                calendar.addEventSource(response.data);
            }
        });

    });

});
