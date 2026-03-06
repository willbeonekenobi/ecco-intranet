jQuery(document).ready(function ($) {

    console.log('ECCO Calendar JS Loaded');

    const select = $('#ecco-group-selector');
    const calendarEl = document.getElementById('ecco-calendar');

    if (!select.length || !calendarEl) {
        console.error('Calendar elements missing');
        return;
    }

    let currentGroup = null;

    const calendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',
        height: 650,

        selectable: true,
        editable: true,
        eventDurationEditable: true,

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },

        timeZone: 'local',

        events: [],

        /* ======================================
           CREATE EVENT
        ====================================== */

        select: function (info) {

            if (!currentGroup) {
                alert('Select a group first');
                return;
            }

            const title = prompt("Event Title");

            if (!title) {
                calendar.unselect();
                return;
            }

            let start = info.start;
            let end = info.end;

            // If user clicked day (month view)
            if (info.allDay) {

                start.setHours(9,0,0);
                end = new Date(start);
                end.setHours(10,0,0);

            }

            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_create_event',
                group_id: currentGroup,
                title: title,
                start: start.toISOString(),
                end: end.toISOString()
            }, function (response) {

                if (!response.success) {
                    alert('Could not create event');
                    return;
                }

                loadEvents(currentGroup);
            });

        },

        /* ======================================
           DRAG / RESIZE EVENT
        ====================================== */

        eventChange: function (info) {

            if (!currentGroup) return;

            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_update_event',
                group_id: currentGroup,
                event_id: info.event.id,
                title: info.event.title,
                start: info.event.start.toISOString(),
                end: info.event.end ? info.event.end.toISOString() : info.event.start.toISOString()
            }, function (response) {

                if (!response.success) {
                    alert('Update failed');
                    info.revert();
                }

            });

        },

        /* ======================================
           CLICK EVENT (EDIT / DELETE)
        ====================================== */

        eventClick: function (info) {

            const action = prompt(
                "Type:\n" +
                "1 = rename\n" +
                "2 = delete\n" +
                "3 = change time"
            );

            /* rename */

            if (action === "1") {

                const newTitle = prompt("New title", info.event.title);

                if (!newTitle) return;

                updateEvent(info.event, newTitle);

            }

            /* delete */

            if (action === "2") {

                if (!confirm("Delete this event?")) return;

                $.post(eccoCalendar.ajax_url, {
                    action: 'ecco_delete_event',
                    group_id: currentGroup,
                    event_id: info.event.id
                }, function (response) {

                    if (!response.success) {
                        alert('Delete failed');
                        return;
                    }

                    info.event.remove();

                });

            }

            /* change time */

            if (action === "3") {

                let newStart = prompt(
                    "Start (YYYY-MM-DD HH:MM)",
                    info.event.start.toISOString().slice(0,16).replace('T',' ')
                );

                if (!newStart) return;

                let newEnd = prompt(
                    "End (YYYY-MM-DD HH:MM)",
                    info.event.end
                        ? info.event.end.toISOString().slice(0,16).replace('T',' ')
                        : newStart
                );

                if (!newEnd) return;

                newStart = new Date(newStart.replace(' ','T'));
                newEnd = new Date(newEnd.replace(' ','T'));

                $.post(eccoCalendar.ajax_url, {
                    action: 'ecco_update_event',
                    group_id: currentGroup,
                    event_id: info.event.id,
                    title: info.event.title,
                    start: newStart.toISOString(),
                    end: newEnd.toISOString()
                }, function (response) {

                    if (!response.success) {
                        alert('Time update failed');
                        return;
                    }

                    loadEvents(currentGroup);

                });

            }

        }

    });

    calendar.render();

    /* ======================================
       LOAD GROUPS
    ====================================== */

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

    /* ======================================
       LOAD EVENTS
    ====================================== */

    function loadEvents(groupId) {

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

    }

    /* ======================================
       GROUP SELECTION
    ====================================== */

    select.on('change', function () {

        currentGroup = $(this).val();

        if (!currentGroup) return;

        console.log('Loading events for group:', currentGroup);

        loadEvents(currentGroup);

    });

});