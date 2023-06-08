<?php
/*
Modify the values before printing to inputs
*/

// collect the attached entities
$author = get_post( $_GET['post'] );

if ( $author->post_type !== 'billing' ) { return; } //++ move it to the class

/*
$wp_query = new WP_Query([
    'author' => $author->post_author,
    'post_type' => ['clinic', 'doctor'],
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'entity-billing',
            'value' => $_GET['post']
        ]
    ],
]);

if ( !$wp_query->have_posts() ) { return; }

$entities = [];
while ( $wp_query->have_posts() ) {
    $wp_query->the_post();
    $entities[] = get_the_title() .
        ' <a href="'.get_the_permalink().'">' . __( 'View' ) . '</a>' .
        ' <a href="'.get_edit_post_link().'">' . __( 'Edit' ) . '</a>'
    ;
}
wp_reset_postdata(); // that's the reason
wp_reset_query(); // that's the reason
//*/
$entity_posts = get_posts([
    'author' => $author->post_author,
    'post_type' => ['clinic', 'doctor'],
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'entity-billing',
            'value' => $_GET['post']
        ]
    ],
]);
$entities = [];
foreach( $entity_posts as $v ){
    $entities[] = $v->post_title .
        ' <a href="'.get_the_permalink( $v->ID ).'">' . __( 'View' ) . '</a>' .
        ' <a href="'.get_edit_post_link( $v->ID ).'">' . __( 'Edit' ) . '</a>'
    ;
}
wp_reset_postdata(); // not needed?
if ( !$entities[0] ) { return; }


FCP_Forms::json_field_by_sibling(
    $this->s->fields,
    'billing-vat',
    [
        'type' => 'notice',
        'text' => '<p>Kliniken und Ärzte zugewiesen:<br>' . implode( '<br>', $entities ) . '</p>',
        'meta_box' => true,
    ],
    'after'
);