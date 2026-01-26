<div class="ecco-leave">

    <h1>Request Leave</h1>

    <form id="ecco-leave-form">

        <label>
            Leave type
            <select name="leave_type" required>
                <option value="">Select leave type</option>
                <option value="annual">Annual Leave</option>
                <option value="sick">Sick Leave</option>
                <option value="unpaid">Unpaid Leave</option>
                <option value="other">Other</option>
            </select>
        </label>

        <div class="ecco-row">
            <label>
                Start date
                <input type="date" name="start_date" required>
            </label>

            <label>
                End date
                <input type="date" name="end_date" required>
            </label>
        </div>

        <div class="ecco-days">
            Total days: <strong><span id="ecco-total-days">0</span></strong>
        </div>

        <label>
            Reason for leave
            <textarea name="reason" required></textarea>
        </label>

        <label>
            Message to manager (optional)
            <textarea name="manager_message"></textarea>
        </label>

        <div id="ecco-doctor-note" style="display:none;">
            <label>
                Upload doctor’s note
                <input type="file" name="doctor_note" accept=".jpg,.jpeg,.png,.pdf">
                <small>Required for sick leave</small>
            </label>
        </div>

        <button type="submit" class="button button-primary">
            Submit leave request
        </button>

    </form>

    <div id="ecco-leave-confirmation" style="display:none;">
        <h3>Request submitted</h3>
        <p>Your manager has been notified.</p>
    </div>

</div>
