jQuery(document).ready(function ($) {

    const select    = $('#ecco-group-selector');
    const calendarEl = document.getElementById('ecco-calendar');

    if (!select.length || !calendarEl) {
        console.error('ECCO Calendar: elements missing');
        return;
    }

    let currentGroup  = null;
    let editingEvent  = null;
    const defaultTz   = eccoCalendar.defaultTz || 'South Africa Standard Time';
    const timezones   = eccoCalendar.timezones  || {};

    /* =========================================================
       BUILD TIMEZONE OPTIONS HTML
       ========================================================= */
    let tzOptionsHtml = '';
    Object.keys(timezones).forEach(function (val) {
        tzOptionsHtml += `<option value="${val}">${timezones[val]}</option>`;
    });

    /* =========================================================
       MODAL HTML
       ========================================================= */
    const modalHTML = `
    <div id="ecco-event-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;overflow-y:auto;">
      <div style="background:#fff;max-width:440px;margin:60px auto;padding:28px;border-radius:8px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <h3 id="ecco-modal-title" style="margin-top:0;">Event</h3>

        <label style="display:block;margin-bottom:14px;">
          <strong>Title</strong>
          <input type="text" id="ecco-event-title" style="display:block;width:100%;margin-top:4px;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;">
        </label>

        <label style="display:block;margin-bottom:14px;">
          <strong>Start</strong>
          <input type="datetime-local" id="ecco-event-start" style="display:block;width:100%;margin-top:4px;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;">
        </label>

        <label style="display:block;margin-bottom:14px;">
          <strong>End</strong>
          <input type="datetime-local" id="ecco-event-end" style="display:block;width:100%;margin-top:4px;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;">
        </label>

        <label style="display:block;margin-bottom:20px;">
          <strong>Timezone</strong>
          <select id="ecco-event-timezone" style="display:block;width:100%;margin-top:4px;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;">
            ${tzOptionsHtml}
          </select>
        </label>

        <div style="display:flex;gap:10px;align-items:center;">
          <button id="ecco-event-save"   style="padding:8px 20px;background:#1565c0;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:600;">Save</button>
          <button id="ecco-event-cancel" style="padding:8px 16px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;font-size:14px;">Cancel</button>
          <button id="ecco-event-delete" style="margin-left:auto;padding:8px 16px;background:#ffebee;color:#c62828;border:1px solid #ffcdd2;border-radius:4px;cursor:pointer;font-size:14px;">Delete</button>
        </div>
      </div>
    </div>`;

    $('body').append(modalHTML);

    const modal     = $('#ecco-event-modal');
    const titleInput = $('#ecco-event-title');
    const startInput = $('#ecco-event-start');
    const endInput   = $('#ecco-event-end');
    const tzSelect   = $('#ecco-event-timezone');
    const saveBtn    = $('#ecco-event-save');
    const cancelBtn  = $('#ecco-event-cancel');
    const deleteBtn  = $('#ecco-event-delete');

    /* Set default timezone option on load */
    tzSelect.val(defaultTz);

    /* =========================================================
       DATE HELPERS
       Produce a datetime-local string (YYYY-MM-DDTHH:MM) from a
       JS Date object WITHOUT converting to UTC first.
       ========================================================= */
    function toLocalInput(date) {
        if (!date) return '';
        const pad = n => String(n).padStart(2, '0');
        return date.getFullYear() + '-' +
               pad(date.getMonth() + 1) + '-' +
               pad(date.getDate()) + 'T' +
               pad(date.getHours()) + ':' +
               pad(date.getMinutes());
    }

    /* =========================================================
       MODAL
       ========================================================= */
    function showModal(event) {
        editingEvent = event || null;
        modal.show();

        if (event && event.id) {
            /* Editing an existing event */
            $('#ecco-modal-title').text('Edit Event');
            titleInput.val(event.title);
            startInput.val(toLocalInput(event.start));
            endInput.val(toLocalInput(event.end || event.start));
            deleteBtn.show();
        } else if (event) {
            /* New event from calendar selection */
            $('#ecco-modal-title').text('New Event');
            titleInput.val('');
            startInput.val(toLocalInput(event.start));
            endInput.val(toLocalInput(event.end || event.start));
            deleteBtn.hide();
        } else {
            $('#ecco-modal-title').text('New Event');
            titleInput.val('');
            startInput.val('');
            endInput.val('');
            deleteBtn.hide();
        }

        /* Keep the last chosen timezone (don't reset to default each time) */
        if (!tzSelect.val()) tzSelect.val(defaultTz);

        titleInput.focus();
    }

    cancelBtn.on('click', function () { modal.hide(); });

    /* Close on backdrop click */
    modal.on('click', function (e) {
        if ($(e.target).is(modal)) modal.hide();
    });

    /* =========================================================
       DELETE
       ========================================================= */
    deleteBtn.on('click', function () {
        if (!editingEvent || !editingEvent.id) return;
        if (!confirm('Delete this event?')) return;

        $.post(eccoCalendar.ajax_url, {
            action:   'ecco_delete_event',
            group_id: currentGroup,
            event_id: editingEvent.id
        }, function (response) {
            if (!response.success) { alert('Delete failed'); return; }
            loadEvents(currentGroup);
            modal.hide();
        });
    });

    /* =========================================================
       SAVE (create or update)
       ========================================================= */
    saveBtn.on('click', function () {
        if (!currentGroup) { alert('Select a group first.'); return; }

        const title = titleInput.val().trim();
        if (!title) { alert('Please enter an event title.'); return; }

        const start = startInput.val();   // already local: "YYYY-MM-DDTHH:MM"
        const end   = endInput.val();
        const tz    = tzSelect.val() || defaultTz;

        if (!start) { alert('Please set a start time.'); return; }
        if (!end)   { alert('Please set an end time.');   return; }
        if (end < start) { alert('End time must be after start time.'); return; }

        const payload = {
            group_id: currentGroup,
            title:    title,
            start:    start,          // plain local datetime string — no UTC conversion
            end:      end,
            timezone: tz,
        };

        if (editingEvent && editingEvent.id) {
            payload.action   = 'ecco_update_event';
            payload.event_id = editingEvent.id;
        } else {
            payload.action = 'ecco_create_event';
        }

        saveBtn.prop('disabled', true).text('Saving…');

        $.post(eccoCalendar.ajax_url, payload, function (response) {
            saveBtn.prop('disabled', false).text('Save');
            if (!response.success) {
                alert(editingEvent && editingEvent.id ? 'Update failed.' : 'Could not create event.');
                return;
            }
            loadEvents(currentGroup);
            modal.hide();
        }).fail(function () {
            saveBtn.prop('disabled', false).text('Save');
            alert('Request failed. Please try again.');
        });
    });

    /* =========================================================
       FULLCALENDAR
       ========================================================= */
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        selectable: true,
        editable: true,
        eventDurationEditable: true,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay'
        },
        timeZone: 'local',
        events: [],

        select: function (info) {
            let start = info.start;
            let end   = info.end;
            if (info.allDay) {
                start = new Date(start);
                start.setHours(9, 0, 0, 0);
                end = new Date(start);
                end.setHours(10, 0, 0, 0);
            }
            showModal({ start, end });
        },

        eventClick: function (info) {
            showModal(info.event);
        },

        /* Drag and drop / resize — send local strings without UTC conversion */
        eventChange: function (info) {
            const tz = tzSelect.val() || defaultTz;
            $.post(eccoCalendar.ajax_url, {
                action:   'ecco_update_event',
                group_id: currentGroup,
                event_id: info.event.id,
                title:    info.event.title,
                start:    toLocalInput(info.event.start),
                end:      toLocalInput(info.event.end || info.event.start),
                timezone: tz,
            }, function (response) {
                if (!response.success) { alert('Update failed'); info.revert(); }
            });
        }
    });

    calendar.render();

    /* =========================================================
       LOAD GROUPS
       ========================================================= */
    $.ajax({
        url:    eccoCalendar.ajax_url,
        method: 'POST',
        data:   { action: 'ecco_get_groups' },
        success: function (response) {
            if (!response.success) {
                select.html('<option>Failed to load groups</option>');
                return;
            }
            select.empty().append('<option value="">— Select Group —</option>');
            response.data.forEach(function (group) {
                select.append($('<option>', { value: group.id, text: group.title }));
            });
        }
    });

    /* =========================================================
       LOAD EVENTS
       ========================================================= */
    function loadEvents(groupId) {
        $.ajax({
            url:    eccoCalendar.ajax_url,
            method: 'POST',
            data:   { action: 'ecco_get_group_events', group_id: groupId },
            success: function (response) {
                if (!response.success) { alert('Could not load events'); return; }
                calendar.removeAllEvents();
                calendar.addEventSource(response.data);
            }
        });
    }

    select.on('change', function () {
        currentGroup = $(this).val();
        if (!currentGroup) { calendar.removeAllEvents(); return; }
        loadEvents(currentGroup);
    });

});
