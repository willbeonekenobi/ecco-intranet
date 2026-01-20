jQuery(document).ready(function ($) {

    // Track current folder per library
    const currentFolders = {};

    function loadLibrary(section, folderId = null) {
        const library = section.data('library');
        currentFolders[library] = folderId;

        section.find('.doc-list').html('Loading…');

        $.post(ECCO.ajax, {
            action: 'ecco_list_docs',
            library: library,
            folder: folderId || ''
        }, function (res) {

            if (!res || !res.value) {
                section.find('.doc-list').html('No documents');
                return;
            }

            let html = '';

            // Back button (only when inside a folder)
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
                        <div class="ecco-folder"
                             data-id="${item.id}"
                             style="cursor:pointer;">
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

    // Initial load for each document library
    $('section[data-library]').each(function () {
        loadLibrary($(this));
    });

    // Folder click
    $(document).on('click', '.ecco-folder', function () {
        const section = $(this).closest('section');
        const folderId = $(this).data('id');
        loadLibrary(section, folderId);
    });

    // Back click
    $(document).on('click', '.ecco-back', function () {
        const section = $(this).closest('section');
        loadLibrary(section, null);
    });

    // Upload handler (uploads into current folder)
$(document).on('click', '.upload-btn', function () {
    const section = $(this).closest('section');
    const library = section.data('library');
    const fileInput = section.find('.upload')[0];
    const file = fileInput.files[0];

    if (!file) {
        alert('Please choose a file first');
        return;
    }

    const folder = currentFolders[library] || '';

    // 🔹 Small files: use existing WordPress upload (unchanged)
    if (file.size < 512 * 1024) {

        const data = new FormData();
        data.append('action', 'ecco_upload');
        data.append('library', library);
        data.append('folder', folder);
        data.append('file', file);

        $.ajax({
            url: ECCO.ajax,
            method: 'POST',
            data: data,
            contentType: false,
            processData: false,
            success: function (res) {
                if (!res || !res.success) {
                    alert('Upload failed');
                    return;
                }
                loadLibrary(section, folder || null);
            }
        });

        return;
    }

    // 🔹 Large files: create upload session
    const progress = $('<div style="height:8px;background:#eee;margin-top:6px;"><div style="height:100%;width:0;background:#2271b1;"></div></div>');
    section.append(progress);
    const bar = progress.find('div');

    $.post(ECCO.ajax, {
        action: 'ecco_upload_session',
        library: library,
        folder: folder,
        filename: file.name,
        filesize: file.size
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
                    'Content-Range': `bytes ${offset}-${offset + chunk.size - 1}/${file.size}`
                },
                data: chunk,
                processData: false,
                contentType: false,
                success: function () {
                    offset += chunk.size;
                    bar.css('width', Math.round(offset / file.size * 100) + '%');

                    if (offset < file.size) {
                        uploadChunk();
                    } else {
                        progress.remove();
                        loadLibrary(section, folder || null);
                    }
                },
                error: function () {
                    alert('Chunk upload failed');
                    progress.remove();
                }
            });
        }

        uploadChunk();
    });
});


});
