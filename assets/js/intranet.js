jQuery(document).ready(function ($) {

    /* ---------------------------------------------------------
     * Folder state per library
     * --------------------------------------------------------- */
    const folderPaths = {};

    function getCurrentFolderId(library) {
        return folderPaths[library]?.slice(-1)[0]?.id || '';
    }

    /* ---------------------------------------------------------
     * Breadcrumb rendering
     * --------------------------------------------------------- */
    function renderBreadcrumbs(library) {
        let html = `<div class="ecco-breadcrumbs" style="margin-bottom:8px;">`;
        html += `<span class="crumb" data-index="-1" style="cursor:pointer;">Home</span>`;

        (folderPaths[library] || []).forEach((folder, index) => {
            html += ` › <span class="crumb" data-index="${index}" style="cursor:pointer;">${folder.name}</span>`;
        });

        html += `</div>`;
        return html;
    }

    /* ---------------------------------------------------------
     * Load library / folder
     * --------------------------------------------------------- */
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

    /* ---------------------------------------------------------
     * Initial load
     * --------------------------------------------------------- */
    $('section[data-library]').each(function () {
        loadLibrary($(this));
    });

    /* ---------------------------------------------------------
     * Folder navigation
     * --------------------------------------------------------- */
    $(document).on('click', '.ecco-folder', function () {
        const section = $(this).closest('section');
        const library = section.data('library');

        folderPaths[library].push({
            id: $(this).data('id'),
            name: $(this).data('name')
        });

        loadLibrary(section, $(this).data('id'));
    });

    /* ---------------------------------------------------------
     * Breadcrumb navigation
     * --------------------------------------------------------- */
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

    /* ---------------------------------------------------------
     * Upload via Graph upload session (ALL files)
     * --------------------------------------------------------- */
    function startUpload(section, file, conflict) {

        const library = section.data('library');
        const folder = getCurrentFolderId(library);

        const progress = $(`
            <div class="ecco-progress" style="height:8px;background:#eee;margin:8px 0;">
                <div style="height:100%;width:0;background:#2271b1;"></div>
            </div>
        `);

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

    /* ---------------------------------------------------------
     * Upload button
     * --------------------------------------------------------- */
    $(document).on('click', '.upload-btn', function () {

    const section = $(this).closest('section');
    const fileInput = section.find('.upload')[0];
    const file = fileInput.files[0];

    if (!file) {
        alert('Please choose a file first');
        return;
    }

    const library = section.data('library');
    const folder =
        (folderPaths[library] && folderPaths[library].length)
            ? folderPaths[library][folderPaths[library].length - 1].id
            : '';

    let conflict = section.find('.ecco-conflict').val();

    // Normalise values for backend
    if (conflict === 'overwrite') conflict = 'replace';
    if (conflict === 'cancel') conflict = 'fail';
    if (!conflict) conflict = 'replace';

    // 🔍 ALWAYS check if file exists FIRST
    $.post(ECCO.ajax, {
        action: 'ecco_file_exists',
        library: library,
        folder: folder,
        filename: file.name
    }, function (res) {

        if (!res || !res.success) {
            alert('Unable to verify file existence');
            return;
        }

        // 🚨 File exists → show confirmation if overwrite
        if (res.data.exists) {

            if (conflict === 'fail') {
                alert('A file with this name already exists. Upload cancelled.');
                return;
            }

            if (conflict === 'replace') {
                const sizeMB = (res.data.size / (1024 * 1024)).toFixed(2);
                const modified = res.data.lastModified
                    ? new Date(res.data.lastModified).toLocaleString()
                    : 'Unknown';

                const ok = confirm(
                    `A file named "${file.name}" already exists.\n\n` +
                    `Size: ${sizeMB} MB\n` +
                    `Last modified: ${modified}\n\n` +
                    `Do you want to overwrite it?`
                );

                if (!ok) {
                    return;
                }
            }
        }

        // ✅ Safe to upload
        startUpload(section, file, conflict);

    });
});


});