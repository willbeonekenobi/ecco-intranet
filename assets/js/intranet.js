jQuery(document).ready(function ($) {

    // Track current folder per library
    const currentFolders = {};

    function loadLibrary(section, folderId = null) {
        const library = section.data('library');
        currentFolders[library] = folderId;

        section.find('.doc-list').html('Loading…');

        $.post(ECCO.ajax, {
            action: 'ecco_list_docs',
            library,
            folder: folderId || ''
        }, function (res) {

            if (!res || !res.value) {
                section.find('.doc-list').html('No documents');
                return;
            }

            let html = '';

            if (folderId) {
                html += `
                    <div class="ecco-back" style="cursor:pointer; margin-bottom:6px;">
                        ⬅ Back
                    </div>
                `;
            }

            res.value.forEach(item => {
                if (item.folder) {
                    html += `
                        <div class="ecco-folder" data-id="${item.id}" style="cursor:pointer;">
                            📁 ${item.name}
                        </div>
                    `;
                } else {
                    html += `
                        <div class="ecco-file">
                            📄 <a href="${item.webUrl}" target="_blank" rel="noopener">
                                ${item.name}
                            </a>
                        </div>
                    `;
                }
            });

            section.find('.doc-list').html(html);
        });
    }

    // Initial load
    $('section[data-library]').each(function () {
        loadLibrary($(this));
    });

    // Folder navigation
    $(document).on('click', '.ecco-folder', function () {
        loadLibrary($(this).closest('section'), $(this).data('id'));
    });

    $(document).on('click', '.ecco-back', function () {
        loadLibrary($(this).closest('section'), null);
    });

    /**
     * Start upload AFTER conflict decision
     */
    function startUpload(section, file, conflict) {

        const library = section.data('library');
        const folder = currentFolders[library] || '';

        // Small files
        if (file.size < 512 * 1024) {

            const data = new FormData();
            data.append('action', 'ecco_upload');
            data.append('library', library);
            data.append('folder', folder);
            data.append('file', file);
            data.append('conflict', conflict);

            $.ajax({
                url: ECCO.ajax,
                method: 'POST',
                data,
                contentType: false,
                processData: false,
                success(res) {
                    if (!res || !res.success) {
                        alert('Upload failed');
                        return;
                    }
                    loadLibrary(section, folder || null);
                }
            });

            return;
        }

        // Large files (chunked)
        const progress = $(`
            <div style="height:8px;background:#eee;margin-top:6px;">
                <div style="height:100%;width:0;background:#2271b1;"></div>
            </div>
        `);

        section.append(progress);
        const bar = progress.find('div');

        $.post(ECCO.ajax, {
            action: 'ecco_upload_session',
            library,
            folder,
            filename: file.name,
            filesize: file.size,
            conflict
        }, function (res) {

            if (!res || !res.success) {
                alert('Failed to start upload');
                progress.remove();
                return;
            }

            const uploadUrl = res.data.uploadUrl;
            const chunkSize = 320 * 1024;
            let offset = 0;

            function uploadChunk() {
                const chunk = file.slice(offset, offset + chunkSize);

                $.ajax({
                    url: uploadUrl,
                    method: 'PUT',
                    headers: {
                        'Content-Range': `bytes ${offset}-${offset + chunk.size - 1}/${file.size}`,
                        'Content-Type': 'application/octet-stream'
                    },
                    data: chunk,
                    processData: false,
                    contentType: false,
                    success() {
                        offset += chunk.size;
                        bar.css('width', Math.round((offset / file.size) * 100) + '%');

                        if (offset < file.size) {
                            uploadChunk();
                        } else {
                            progress.remove();
                            loadLibrary(section, folder || null);
                        }
                    },
                    error() {
                        alert('Chunk upload failed');
                        progress.remove();
                    }
                });
            }

            uploadChunk();
        });
    }

    /**
     * Upload button click
     */
    $(document).on('click', '.upload-btn', function () {

        const section = $(this).closest('section');
        const fileInput = section.find('.upload')[0];
        const file = fileInput.files[0];

        if (!file) {
            alert('Please choose a file first');
            return;
        }

        const library = section.data('library');
        const folder = currentFolders[library] || '';

        let conflict = section.find('.ecco-conflict').val();

        // Normalise UI values → Graph values
        if (conflict === 'overwrite') conflict = 'replace';
        if (conflict === 'cancel') conflict = 'fail';
        if (!conflict) conflict = 'rename';

        // CANCEL IF EXISTS
        if (conflict === 'fail') {

            $.post(ECCO.ajax, {
                action: 'ecco_file_exists',
                library,
                folder,
                filename: file.name
            }, function (res) {

                if (!res || !res.success) {
                    alert('Unable to verify file existence');
                    return;
                }

                if (res.data.exists) {
                    alert('A file with this name already exists. Upload cancelled.');
                    return;
                }

                startUpload(section, file, 'rename');
            });

            return;
        }

        // OVERWRITE WITH CONFIRMATION
        if (conflict === 'replace') {

            $.post(ECCO.ajax, {
                action: 'ecco_file_exists',
                library,
                folder,
                filename: file.name
            }, function (res) {

                if (!res || !res.success) {
                    alert('Unable to verify file existence');
                    return;
                }

                if (res.data.exists) {

                    const sizeMB = (res.data.size / (1024 * 1024)).toFixed(2);
                    const modified = res.data.lastModified
                        ? new Date(res.data.lastModified).toLocaleString()
                        : 'Unknown';

                    if (!confirm(
                        `A file named "${file.name}" already exists.\n\n` +
                        `Size: ${sizeMB} MB\n` +
                        `Last modified: ${modified}\n\n` +
                        `Do you want to overwrite it?`
                    )) return;
                }

                startUpload(section, file, 'replace');
            });

            return;
        }

        // RENAME (default)
        startUpload(section, file, 'rename');
    });

});
