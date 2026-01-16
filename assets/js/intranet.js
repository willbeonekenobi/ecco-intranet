jQuery(document).ready(function ($) {
    $('section').each(function () {
        const section = $(this);
        const library = section.data('library');

        $.post(ECCO.ajax, {
            action: 'ecco_list_docs',
            nonce: ECCO.nonce,
            library: library
        }, function (res) {
            if (!res.value) {
                section.find('.doc-list').html('No documents');
                return;
            }

            section.find('.doc-list').html(
    res.value.map(item => {
        if (item.folder) {
            return `<div>📁 ${item.name}</div>`;
        }

        return `
            <div>
                📄 <a href="${item.webUrl}" target="_blank" rel="noopener">
                    ${item.name}
                </a>
            </div>
        `;
    }).join('')
);

        });

        section.find('.upload-btn').on('click', function () {
            const file = section.find('.upload')[0].files[0];
            if (!file) return;

            const data = new FormData();
            data.append('action', 'ecco_upload');
            data.append('nonce', ECCO.nonce);
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
});
