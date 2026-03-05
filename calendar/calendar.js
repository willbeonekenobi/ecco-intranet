jQuery(document).ready(function ($) {

    console.log('ECCO Calendar JS Loaded');

    const select = $('#ecco-group-selector');
    const calendarEl = document.getElementById('ecco-calendar');

    if (!select.length || !calendarEl) {
        console.error('Calendar elements missing');
        return;
    }

    let currentGroup = null;

    // 🔹 Initialize FullCalendar
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

        events: [],

        // 🔹 Create Event
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

            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_create_event',
                group_id: currentGroup,
                title: title,
                start: info.startStr,
                end: info.endStr
            }, function (response) {

                if (!response.success) {
                    alert('Could not create event');
                    return;
                }

                loadEvents(currentGroup);
            });

        },

        // 🔹 Drag / Resize Event
        eventChange: function (info) {

            if (!currentGroup) return;

            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_update_event',
                group_id: currentGroup,
                event_id: info.event.id,
                title: info.event.title,
                start: info.event.start.toISOString(),
                end: info.event.end ? info.event.end.toISOString() : null
            }, function (response) {

                if (!response.success) {
                    alert('Update failed');
                    info.revert();
                }

            });

        },

        // 🔹 Click Event (Edit / Delete)
        eventClick: function (info) {

            const action = prompt(
                "Type:\n" +
                "1 to rename event\n" +
                "2 to delete event"
            );

            if (action === "1") {

                const newTitle = prompt("New title", info.event.title);

                if (!newTitle) return;

                $.post(eccoCalendar.ajax_url, {
                    action: 'ecco_update_event',
                    group_id: currentGroup,
                    event_id: info.event.id,
                    title: newTitle,
                    start: info.event.start.toISOString(),
                    end: info.event.end ? info.event.end.toISOString() : null
                }, function (response) {

                    if (!response.success) {
                        alert('Rename failed');
                        return;
                    }

                    loadEvents(currentGroup);
                });

            }

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

        }

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

    // 🔹 Load Events
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

    // 🔹 Group Selection
    select.on('change', function () {

        currentGroup = $(this).val();

        if (!currentGroup) return;

        console.log('Loading events for group:', currentGroup);

        loadEvents(currentGroup);

    });

});