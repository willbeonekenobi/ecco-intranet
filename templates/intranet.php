<h1>ECCO Intranet</h1>

<?php foreach (ecco_library_map() as $key => $name): ?>
    <section data-library="<?= esc_attr($key) ?>">
        <h2><?= esc_html($name) ?></h2>

        <div class="doc-list">Loading…</div>

        <input type="file" class="upload">
        <button class="upload-btn">Upload</button>
    </section>
<?php endforeach; ?>
