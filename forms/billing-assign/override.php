<?php
/*
Print something else instead of the form
*/

if ( !is_user_logged_in() ) {
    unset( $json->fields );
    $override = '';
    return;
}


// pick all billings of current user
$wp_query = new WP_Query([
    'author' => wp_get_current_user()->ID,
    'post_type' => 'billing',
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'any',
    'posts_per_page' => -1,
]);

if ( !$wp_query->have_posts() ) {
    unset( $json->fields );
    $override = '';
    return;
}


// add billing the options
$billings = [];
while ( $wp_query->have_posts() ) {
    $wp_query->the_post();
    $billings[ get_the_ID() ] = get_the_title();
}
wp_reset_query();

FCP_Forms::json_attr_by_name( $json->fields,
    'billing-id',
    'options',
    $billings
);


// advice the entity option
if ( isset( $_GET['step3'] ) ) {

    // pick the newes entity meta
    $wp_query = new WP_Query([
        'author' => wp_get_current_user()->ID,
        'post_type' => ['clinic', 'doctor'],
        'orderby' => 'ID',
        'order'   => 'DESC',
        'post_status' => 'any',
        'posts_per_page' => 1,
    ]);

    if ( $wp_query->have_posts() ) {
        while ( $wp_query->have_posts() ) {
            $wp_query->the_post();

            // replace select with a hidden and a notice
            FCP_Forms::json_field_by_sibling( $json->fields,
                'entity-id',
                [
                    'type' => 'hidden',
                    'name' => 'entity-id',
                    'value' => get_the_ID()
                ],
                'override'
            );
            FCP_Forms::json_field_by_sibling( $json->fields,
                'entity-id',
                [
                    'type' => 'notice',
                    'text' => 'Wird ausgestellt an: ' . get_the_title()
                ],
                'after'
            );

            // just a notice
            FCP_Forms::json_field_by_sibling( $json->fields,
                'billing-submit',
                [
                    'type' => 'notice',
                    'text' => '<p>Nach Bestätigung der Registrierung erhalten Sie in Kürze eine Rechnung.</p>',
                ],
                'before'
            );

            break;
        }
        wp_reset_query();
    }
    
    return;
}

// pick all user's entities
$wp_query = new WP_Query([
    'author' => wp_get_current_user()->ID,
    'post_type' => ['clinic', 'doctor'],
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'any',
    'posts_per_page' => -1,
]);

if ( $wp_query->have_posts() ) {

    $entities = [];
    while ( $wp_query->have_posts() ) {
        $wp_query->the_post();
        $entities[ get_the_ID() ] = get_the_title();
    }
    wp_reset_query();
    
    FCP_Forms::json_attr_by_name( $json->fields,
        'entity-id',
        'options',
        $entities
    );
    
}