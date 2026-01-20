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
        const file = section.find('.upload')[0].files[0];

        if (!file) {
            alert('Please choose a file first');
            return;
        }

        const data = new FormData();
        data.append('action', 'ecco_upload');
        data.append('library', library);
        data.append('folder', currentFolders[library] || '');
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
                loadLibrary(section, currentFolders[library] || null);
            }
        });
    });

});
