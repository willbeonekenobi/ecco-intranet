jQuery(document).ready(function ($) {

    const folderPaths = {};

    function getCurrentFolderId(library) {
        return folderPaths[library]?.slice(-1)[0]?.id || '';
    }

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
     * SAFE DATE EXTRACTION (Graph-compatible)
     * --------------------------------------------------------- */
    function getDates(item) {

        const created =
            item.createdDateTime ||
            item.fileSystemInfo?.createdDateTime ||
            null;

        const modified =
            item.lastModifiedDateTime ||
            item.fileSystemInfo?.lastModifiedDateTime ||
            null;

        return {
            created: created ? new Date(created).toLocaleString() : '',
            modified: modified ? new Date(modified).toLocaleString() : ''
        };
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

        console.log('ECCO docs response:', res);

        // 🔥 Handle BOTH raw and wp_send_json_success formats
        const items =
            res?.value ||
            res?.data?.value ||
            [];

        if (!items.length) {
            section.find('.doc-list').html('No documents');
            return;
        }

        let html = renderBreadcrumbs(library);

        items.forEach(item => {

            const { created, modified } = getDates(item);

            const dates = `
                <span style="opacity:0.7;font-size:12px;white-space:nowrap;">
                    ${created ? `Created: ${created}` : ''}
                    ${created && modified ? ' | ' : ''}
                    ${modified ? `Modified: ${modified}` : ''}
                </span>
            `;

            if (item.folder) {

                html += `
                    <div class="ecco-folder"
                         data-id="${item.id}"
                         data-name="${item.name}"
                         style="cursor:pointer; display:flex; justify-content:space-between; gap:12px;">
                        <span>📁 ${item.name}</span>
                        ${dates}
                    </div>
                `;

            } else {

                html += `
                    <div class="ecco-file"
                         style="display:flex; justify-content:space-between; gap:12px;">
                        <span>
                            📄 <a href="${item.webUrl}" target="_blank" rel="noopener">
                                ${item.name}
                            </a>
                        </span>
                        ${dates}
                    </div>
                `;
            }
        });

        section.find('.doc-list').html(html);
    });
}

    $('section[data-library]').each(function () {
        loadLibrary($(this));
    });

    $(document).on('click', '.ecco-folder', function () {
        const section = $(this).closest('section');
        const library = section.data('library');

        folderPaths[library].push({
            id: $(this).data('id'),
            name: $(this).data('name')
        });

        loadLibrary(section, $(this).data('id'));
    });

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

    /* ---------------- Upload code unchanged ---------------- */

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

        if (conflict === 'overwrite') conflict = 'replace';
        if (conflict === 'cancel') conflict = 'fail';
        if (!conflict) conflict = 'replace';

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

                    if (!ok) return;
                }
            }

            startUpload(section, file, conflict);
        });
    });

});
