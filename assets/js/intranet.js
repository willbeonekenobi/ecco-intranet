jQuery(document).ready(function ($) {

    function loadLibrary(section, folderId = null) {
        const library = section.data('library');
        section.find('.doc-list').html('Loading…');

        $.post(ECCO.ajax, {
            action: 'ecco_list_docs',
            library: library,
            folder: folderId
        }, function (res) {

            if (!res || !res.value) {
                section.find('.doc-list').html('No documents');
                return;
            }

            let html = '';

            // Back button (only when inside a folder)
            if (folderId) {
                html += `<div class="ecco-back" style="cursor:pointer">⬅ Back</div>`;
            }

            res.value.forEach(item => {
                if (item.folder) {
                    html += `
                        <div class="ecco-folder"
                             data-id="${item.id}"
                             style="cursor:pointer">
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

    // Upload handler (unchanged, but reattached safely)
    $(document).on('click', '.upload-btn', function () {
        const section = $(this).closest('section');
        const library = section.data('library');
        const file = section.find('.upload')[0].files[0];
        if (!file) return;

        const data = new FormData();
        data.append('action', 'ecco_upload');
        data.append('library', library);
        data.append('file', file);

        $.ajax({
            url: ECCO.ajax,
            method: 'POST',
            data,
            contentType: false,
            processData: false,
            success: () => location.reload()
        });
    });

});
