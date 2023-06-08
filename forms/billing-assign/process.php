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
    $warning = __( 'You don\'t have permission to add / edit a billing data', 'fcpfo' );
    return;
}

if ( $warning || !empty( $warns->result ) ) {
    return;
}

// assign a billing to an entity
$entity = new WP_Query([ // the entity author is current user
    'author'         => wp_get_current_user()->ID,
    'post_type'      => ['clinic', 'doctor'],
    'p'              => $_POST['entity-id'],
    'posts_per_page' => 1,
    'post_status'      => 'any',
]);

if ( !$entity->have_posts() ) { return; }


$billing = new WP_Query([ // the billing author is current user
    'author'         => wp_get_current_user()->ID,
    'post_type'      => 'billing',
    'p'              => $_POST['billing-id'],
    'posts_per_page' => 1,
    'post_status'      => 'any',
]);

if ( !$billing->have_posts() ) { return; }

// assign
update_post_meta( $entity->posts[0]->ID, 'entity-billing', $billing->posts[0]->ID );

// mark the tariff as paid and pending
$tariff = get_post_meta( $entity->posts[0]->ID, 'entity-tariff-tmp', true );
delete_post_meta( $entity->posts[0]->ID, 'entity-tariff-tmp', $tariff );
update_post_meta( $entity->posts[0]->ID, 'entity-tariff', $tariff );
update_post_meta( $entity->posts[0]->ID, 'entity-payment-status', 'pending' );

// request the bill
require_once __DIR__ . '/../../mail/mail.php';
FCP_FormsMail::to_accountant( 'request', $entity->posts[0]->ID );


// REDIRECT

if ( $_POST['entity-id'] ) { // if ( isset( $_GET['step3'] ) ) {

    // pick the latest entity
    $entity = new WP_Query([
        'author' => wp_get_current_user()->ID,
        'post_type' => ['clinic', 'doctor'],
        'orderby' => 'ID',
        'order'   => 'DESC',
        'post_status' => 'any',
        'posts_per_page' => 1,
    ]);
    if ( $entity->have_posts() && $entity->posts[0]->ID == $_POST['entity-id'] ) {
        $redirect = get_permalink( $entity->posts[0]->ID ); // the entity post
        return;
    }
}

$redirect = get_edit_post_link( $billing->posts[0]->ID, '' ); // edit the billing link // '' is for redirect - & stays &, not &amp;