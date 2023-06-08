<?php
/*
Process meta boxes data
*/

// the billing method must belong to the delegate
$wp_query = new WP_Query([
    'post_type'      => 'fct-section',
    'p'              => $_POST['custom-header'],
    'posts_per_page' => 1,
    'post_status'    => 'publish',
]);

if ( $wp_query->have_posts() ) {
    // add the option to pass the saving filters
    // ++keep the initial, instead of emptying
    FCP_Forms::json_attr_by_name( $this->s->fields, 'custom-header', 'options', (object) [
        $_POST['custom-header'] => ''
    ]);
}