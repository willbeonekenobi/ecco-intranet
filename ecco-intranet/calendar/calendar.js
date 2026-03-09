jQuery(document).ready(function ($) {

    console.log('ECCO Calendar JS Loaded');

    const select = $('#ecco-group-selector');
    const calendarEl = document.getElementById('ecco-calendar');

    if (!select.length || !calendarEl) {
        console.error('Calendar elements missing');
        return;
    }

    let currentGroup = null;
    let editingEvent = null;

    // 🔹 Modal HTML
    const modalHTML = `
    <div id="ecco-event-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;">
      <div style="background:#fff;max-width:400px;margin:50px auto;padding:20px;border-radius:8px;position:relative;">
        <h3 id="ecco-modal-title">Event</h3>
        <label>Title:<br><input type="text" id="ecco-event-title" style="width:100%"></label><br><br>
        <label>Start:<br><input type="datetime-local" id="ecco-event-start" style="width:100%"></label><br><br>
        <label>End:<br><input type="datetime-local" id="ecco-event-end" style="width:100%"></label><br><br>
        <button id="ecco-event-save">Save</button>
        <button id="ecco-event-cancel">Cancel</button>
        <button id="ecco-event-delete" style="float:right;color:red;">Delete</button>
      </div>
    </div>`;
    $('body').append(modalHTML);

    const modal = $('#ecco-event-modal');
    const titleInput = $('#ecco-event-title');
    const startInput = $('#ecco-event-start');
    const endInput = $('#ecco-event-end');
    const saveBtn = $('#ecco-event-save');
    const cancelBtn = $('#ecco-event-cancel');
    const deleteBtn = $('#ecco-event-delete');

    function showModal(event) {
        editingEvent = event || null;
        modal.show();

        if (event && event.id) {
            // existing event
            $('#ecco-modal-title').text('Edit Event');
            titleInput.val(event.title);
            startInput.val(event.start.toISOString().slice(0,16));
            endInput.val(event.end ? event.end.toISOString().slice(0,16) : event.start.toISOString().slice(0,16));
            deleteBtn.show();
        } else if (event) {
            // new event object from select
            $('#ecco-modal-title').text('New Event');
            titleInput.val('');
            startInput.val(event.start.toISOString().slice(0,16));
            endInput.val(event.end.toISOString().slice(0,16));
            deleteBtn.hide();
        } else {
            $('#ecco-modal-title').text('New Event');
            titleInput.val('');
            startInput.val('');
            endInput.val('');
            deleteBtn.hide();
        }
    }

    cancelBtn.on('click', function () { modal.hide(); });

    deleteBtn.on('click', function () {
        if (!editingEvent || !editingEvent.id || !confirm('Delete this event?')) return;
        $.post(eccoCalendar.ajax_url, {
            action: 'ecco_delete_event',
            group_id: currentGroup,
            event_id: editingEvent.id
        }, function (response) {
            if (!response.success) { alert('Delete failed'); return; }
            loadEvents(currentGroup);
            modal.hide();
        });
    });

    saveBtn.on('click', function () {
        if (!currentGroup) { alert('Select a group first'); return; }
        const title = titleInput.val(); if (!title) { alert('Title required'); return; }
        const start = new Date(startInput.val());
        const end = new Date(endInput.val());

        if (editingEvent && editingEvent.id) {
            // 🔹 Update existing
            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_update_event',
                group_id: currentGroup,
                event_id: editingEvent.id,
                title: title,
                start: start.toISOString(),
                end: end.toISOString()
            }, function(response) {
                if (!response.success) { alert('Update failed'); return; }
                loadEvents(currentGroup);
            });
        } else {
            // 🔹 Create new
            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_create_event',
                group_id: currentGroup,
                title: title,
                start: start.toISOString(),
                end: end.toISOString()
            }, function(response) {
                if (!response.success) { alert('Could not create event'); return; }
                loadEvents(currentGroup);
            });
        }

        modal.hide();
    });

    // 🔹 FullCalendar
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
        select: function(info) {
            let start = info.start;
            let end = info.end;
            if (info.allDay) { start.setHours(9,0,0); end = new Date(start); end.setHours(10,0,0); }
            showModal({ start: start, end: end });
        },
        eventClick: function(info) { showModal(info.event); },
        eventChange: function(info) {
            // drag / resize
            $.post(eccoCalendar.ajax_url, {
                action: 'ecco_update_event',
                group_id: currentGroup,
                event_id: info.event.id,
                title: info.event.title,
                start: info.event.start.toISOString(),
                end: info.event.end ? info.event.end.toISOString() : info.event.start.toISOString()
            }, function(response) {
                if (!response.success) { alert('Update failed'); info.revert(); }
            });
        }
    });

    calendar.render();

    // 🔹 Load Groups
    $.ajax({
        url: eccoCalendar.ajax_url,
        method: 'POST',
        data: { action: 'ecco_get_groups' },
        success: function(response) {
            if (!response.success) { select.html('<option>Failed to load groups</option>'); return; }
            select.empty(); select.append('<option value="">Select Group</option>');
            response.data.forEach(function(group) {
                select.append($('<option>', { value: group.id, text: group.title }));
            });
        }
    });

    function loadEvents(groupId) {
        $.ajax({
            url: eccoCalendar.ajax_url,
            method: 'POST',
            data: { action: 'ecco_get_group_events', group_id: groupId },
            success: function(response) {
                if (!response.success) { alert('Could not load events'); return; }
                calendar.removeAllEvents();
                calendar.addEventSource(response.data);
            }
        });
    }

    select.on('change', function() {
        currentGroup = $(this).val();
        if (!currentGroup) return;
        loadEvents(currentGroup);
    });

});
