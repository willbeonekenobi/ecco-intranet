<h1>ECCO Intranet</h1>

<?php foreach (ecco_library_map() as $key => $name): ?>
<section data-library="<?= esc_attr($key) ?>">
    <h2><?= esc_html($name) ?></h2>

    <div class="doc-list">Loading…</div>

    <select class="ecco-conflict">
        <option value="replace" selected>Overwrite existing</option>
        <option value="rename">Rename if exists</option>
        <option value="cancel">Cancel if exists</option>
    </select>

    <input type="file" class="upload">
    <button class="upload-btn">Upload</button>
</section>
<?php endforeach; ?>
