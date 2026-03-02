document.addEventListener('DOMContentLoaded', function() {

    let calendarEl = document.getElementById('ecco-calendar');

    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        editable: true,
        selectable: true,

        events: function(fetchInfo, successCallback) {
            let groupId = document.getElementById('ecco-group-selector').value;
            if (!groupId) return;

            jQuery.post(ajaxurl, {
                action: 'ecco_get_events',
                group_id: groupId
            }, function(data) {

                let events = data.map(e => ({
                    id: e.id,
                    title: e.subject,
                    start: e.start.dateTime,
                    end: e.end.dateTime
                }));

                successCallback(events);
            });
        },

        select: function(info) {
            let title = prompt("Event Title");
            if (!title) return;

            let groupId = document.getElementById('ecco-group-selector').value;

            let eventData = {
                subject: title,
                start: { dateTime: info.startStr, timeZone: "UTC" },
                end:   { dateTime: info.endStr,   timeZone: "UTC" }
            };

            jQuery.post(ajaxurl, {
                action: 'ecco_save_event',
                group_id: groupId,
                event_data: JSON.stringify(eventData)
            }, function() {
                calendar.refetchEvents();
            });
        },

        eventClick: function(info) {
            if (confirm("Delete event?")) {
                let groupId = document.getElementById('ecco-group-selector').value;

                jQuery.post(ajaxurl, {
                    action: 'ecco_delete_event',
                    group_id: groupId,
                    event_id: info.event.id
                }, function() {
                    calendar.refetchEvents();
                });
            }
        }
    });

    calendar.render();

    jQuery.post(ajaxurl, { action: 'ecco_get_groups' }, function(data) {
        let selector = document.getElementById('ecco-group-selector');
        data.forEach(g => {
            let opt = document.createElement('option');
            opt.value = g.id;
            opt.text  = g.displayName;
            selector.appendChild(opt);
        });
    });

    document.getElementById('ecco-group-selector')
        .addEventListener('change', () => calendar.refetchEvents());
});