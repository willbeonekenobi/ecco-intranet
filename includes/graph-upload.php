<?php
if (!defined('ABSPATH')) exit;

function ecco_graph_upload_large_file($drive_item_path, $file_path) {
    $file_size = filesize($file_path);

    $session = ecco_graph_post(
        $drive_item_path . ':/createUploadSession',
        [
            'item' => [
                '@microsoft.graph.conflictBehavior' => 'replace'
            ]
        ]
    );

    if (empty($session['uploadUrl'])) {
        error_log('ECCO upload session failed');
        return false;
    }

    $upload_url = $session['uploadUrl'];
    $chunk_size = 320 * 1024; // 320KB
    $handle = fopen($file_path, 'rb');
    $offset = 0;

    while (!feof($handle)) {
        $chunk = fread($handle, $chunk_size);
        $chunk_len = strlen($chunk);

        $end = $offset + $chunk_len - 1;

        $range = "bytes $offset-$end/$file_size";

        $status = ecco_graph_put_raw($upload_url, $chunk, $range);

        if (!in_array($status, [200, 201, 202])) {
            fclose($handle);
            error_log("ECCO upload failed at $range");
            return false;
        }

        $offset += $chunk_len;
    }

    fclose($handle);
    return true;
}
