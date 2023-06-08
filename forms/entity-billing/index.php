<?php

// meta select for 

if ( !class_exists( 'FCP_Add_Meta_Boxes' ) ) {
    include_once $this->self_path . 'classes/add-meta-boxes.class.php';
}
if ( !class_exists( 'FCP_Forms__Draw' ) ) {
    include_once $this->self_path . 'classes/draw-fields.class.php';
}

// company name to the billings table
add_filter( 'manage_billing_posts_columns', function( $columns ) {
    $ncolumns = [];
    foreach ( $columns as $k => $v ) {
        $ncolumns[ $k ] = $v;
        if ( $k === 'title' ) {
            $ncolumns['company'] = 'Unternehmens'; // ++add __()
        }
    }
        return $ncolumns;
});
add_action( 'manage_billing_posts_custom_column' , function( $column, $post_id ) {
    switch ( $column ) {
        case 'company' :
            echo get_post_meta( $post_id , 'billing-company' , true );
            break;
    }
}, 10, 2 );

$json = FCP_Forms::structure( $dir );
if ( $json !== false ) {

    new FCP_Add_Meta_Boxes( $json, (object) [
        'title' => 'Billing Details', // translation goes in 'add_meta_boxes' action, as too early now
        'text_domain' => 'fcpfo',
        'post_types' => ['clinic', 'doctor'],
        'context' => 'side',
        'priority' => 'default',
    ] );

}

// ++--not needed if Worthy plugin is not used - removes the Worthy column from the list of billings
add_filter( 'manage_billing_posts_columns', function( $columns ) {
    unset( $columns['worthy'] );
    return $columns;
});