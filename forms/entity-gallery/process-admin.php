<?php
/*
Process meta boxes data
*/

// upload files
$dir = wp_get_upload_dir()['basedir'] . '/entity/' . $postID . '/gallery';

if ( !$uploads->upload([
    'gallery-images' => $dir
])) {
    return;
}

$_POST = $_POST + $uploads->format_for_storing();

// ++wp-admin uploading like with text fields - keeps untouched if something is wrong, but it gotta not interfere the deleting or uploading the correct files
