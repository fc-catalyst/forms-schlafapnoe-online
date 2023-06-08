<?php
/*
Process the form data
*/

if ( !is_user_logged_in() ) {
    $warning = __( 'Please log in to use the form', 'fcpfo' );
    return;
}

// if can create this post type
if ( !get_userdata( wp_get_current_user()->ID )->allcaps['edit_entities'] ) {
    $warning = __( 'You don\'t have permission to add / edit a clinic or a doctor', 'fcpfo-ea' );
    return;
}

// upload to tmp dir
if ( !$uploads->upload_tmp() ) {
    return;
}


// custom address validation - can only be with street_number
if ( empty( $_POST[ 'entity-geo-street_number' ] ) && strpos( $_SERVER['HTTP_HOST'], '.' ) !== false ) { // && not local server
    $warns->add_result( 'entity-address', __( 'The address must have a street number', 'fcpfo-ea' ) );
}

// custom workhours validation - can only have pairs open-close
$schedule_fields = [
    'entity-mo',
    'entity-tu',
    'entity-we',
    'entity-th',
    'entity-fr',
    'entity-sa',
    'entity-su',
];
foreach ( $schedule_fields as $v ) {
    if ( $_POST[ $v.'-open' ][0] && !$_POST[ $v.'-close' ][0] || $_POST[ $v.'-open' ][1] && !$_POST[ $v.'-close' ][1] ) {
        $warns->add_result( $v.'-close', __( 'Please add the closing time', 'fcpfo-ea' ) );
    }
    if ( !$_POST[ $v.'-open' ][0] && $_POST[ $v.'-close' ][0] || !$_POST[ $v.'-open' ][1] && $_POST[ $v.'-close' ][1] ) {
        $warns->add_result( $v.'-open', __( 'Please add the opening time', 'fcpfo-ea' ) );
    }
}


if ( $warning || !empty( $warns->result ) ) {
    return;
}

// custom $_POST filters

// create new post
$id = wp_insert_post( [
    'post_title' => sanitize_text_field( $_POST['entity-name'] ),
    'post_content' => '',
    'post_status' => 'pending',
    'post_author' => wp_get_current_user()->ID,
    'post_type' => $_POST['entity-entity'], // clinic or doctor
    'comment_status' => 'open'
]);
// meta boxes are filled automatically with save_post hooked

if ( $id === 0 ) {
    $warning = __( 'Unexpected WordPress error', 'fcpfo' );
    return;
}

// upload files
$dir = wp_get_upload_dir()['basedir'] . '/entity/' . $id;

if ( !$uploads->upload_tmp_main([
    'entity-avatar' => $dir
])) {
    //$redirect = get_edit_post_link( $id, '' );
    return;
}
//print_r( $uploads->warns ); exit;

$update_list = $uploads->format_for_storing();
foreach ( $update_list as $k => $v ) {
    update_post_meta( $id, $k, $v );
}

/* moved to index.php to a status-transition hook.. ++recheck, maybe
// notify the moderator
require_once __DIR__ . '/../../mail/mail.php';
FCP_FormsMail::to_moderator( 'entity_added', $id );
//*/

// REDIRECT
if ( $_POST['entity-tariff'] === 'kostenloser_eintrag' ) {
    $redirect = get_permalink( $id ); // preview the post
    return;
}

update_post_meta( $id, 'entity-tariff-tmp', $_POST['entity-tariff'] ); // just passing paid tariff to  billing-/process.php

$redirect = get_option( 'siteurl' ) . '/rechnungsdaten-hinterlegen/?step3'; // add the billing method