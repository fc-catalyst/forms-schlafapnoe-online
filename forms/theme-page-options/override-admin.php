<?php
/*
Modify the values before printing to inputs
*/

// get the list of section
$section_posts = get_posts([
    'post_type' => 'fct-section',
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'publish',
    'posts_per_page' => -1,
]);
$sections = [];
foreach( $section_posts as $v ){
    $sections[ $v->ID ] = $v->post_title;
}

FCP_Forms::json_attr_by_name( $this->s->fields, 'custom-header', 'options', $sections );