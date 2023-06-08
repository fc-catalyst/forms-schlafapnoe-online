<?php
/*
Modify the values before printing to inputs
*/

// collect the attached entities
$author = get_post( $_GET['post'] );

if ( !in_array( $author->post_type, ['clinic', 'doctor'] ) ) { return; } //++ move it to the class

// get the billing options
$billing_posts = get_posts([
    'author' => $author->post_author,
    'post_type' => 'billing',
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => ['any', 'active'],
    'posts_per_page' => -1,
]);

// ++can select billings by meta billing-company and author to set instead of no title

$billings = [];
foreach( $billing_posts as $v ){
    $billings[ $v->ID ] = $v->post_title ? $v->post_title : __( '(no title)' );
}

if ( empty( $billings) ) {
    FCP_Forms::json_field_by_sibling(
        $this->s->fields,
        'entity-billing',
        [
            'type' => 'notice',
            'text' => '<p><a href="/wp-admin/post-new.php?post_type=billing" target="_blank">' . __( 'Add New Billing', 'fcpfo' ) . '</a></p>',
            'meta_box' => true,
        ],
        'override'
    );
    return;
}

FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-billing', 'options', $billings );