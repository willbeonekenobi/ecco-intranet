jQuery(document).ready(function ($) {

    // Track folder path per library
    const folderPaths = {};

    function renderBreadcrumbs(library) {
        let html = `<div class="ecco-breadcrumbs" style="margin-bottom:6px;">`;
        html += `<span class="crumb" data-index="-1" style="cursor:pointer;">Home</span>`;

        (folderPaths[library] || []).forEach((folder, index) => {
            html += ` › <span class="crumb" data-index="${index}" style="cursor:pointer;">${folder.name}</span>`;
        });

        html += `</div>`;
        return html;
    }

    function loadLibrary(section, folderId = null) {
        const library = section.data('library');

        if (!folderPaths[library]) {
            folderPaths[library] = [];
        }

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

            let html = renderBreadcrumbs(library);

            res.value.forEach(item => {
                if (item.folder) {
                    html += `
                        <div class="ecco-folder"
                             data-id="${item.id}"
                             data-name="${item.name}"
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

    // Initial load
    $('section[data-library]').each(function () {
        loadLibrary($(this));
    });

    // Folder navigation
    $(document).on('click', '.ecco-folder', function () {
        const section = $(this).closest('section');
        const library = section.data('library');

        if (!folderPaths[library]) {
            folderPaths[library] = [];
        }

        folderPaths[library].push({
            id: $(this).data('id'),
            name: $(this).data('name')
        });

        loadLibrary(section, $(this).data('id'));
    });

    // Breadcrumb navigation
    $(document).on('click', '.crumb', function () {
        const section = $(this).closest('section');
        const library = section.data('library');
        const index = parseInt($(this).data('index'), 10);

        if (index === -1) {
            folderPaths[library] = [];
            loadLibrary(section, null);
            return;
        }

        folderPaths[library] = folderPaths[library].slice(0, index + 1);
        loadLibrary(section, folderPaths[library][index].id);
    });

    /**
     * Start upload AFTER conflict decision
     */
    function startUpload(section, file, conflict) {

        const library = section.data('library');
        const folder = folderPaths[library]?.slice(-1)[0]?.id || '';

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
        <div class="ecco-progress" style="height:8px;background:#eee;margin:6px 0;">
        <div style="height:100%;width:0;background:#2271b1;"></div>
        </div>
`       );

        section.find('.doc-list').before(progress);
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
        const folder = folderPaths[library]?.slice(-1)[0]?.id || '';

        let conflict = section.find('.ecco-conflict').val();

        if (conflict === 'overwrite') conflict = 'replace';
        if (conflict === 'cancel') conflict = 'fail';
        if (!conflict) conflict = 'rename';

        startUpload(section, file, conflict);
    });

});
