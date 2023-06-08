<?php
/*
Process meta boxes data
*/

// the billing method must belong to the delegate
$wp_query = new WP_Query([
    'author'         => $post->post_author,
    'post_type'      => 'billing',
    'p'              => $_POST['entity-billing'],
    'posts_per_page' => 1,
    'post_status'    => ['any', 'active'],
]);

if ( $wp_query->have_posts() ) {
    // add the option to pass the saving filters
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-billing', 'options', (object) [ // ++keep the initial, instead of emptying
        $_POST['entity-billing'] => ''
    ]);
}