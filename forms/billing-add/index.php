<?php

// post type for billing

if ( !class_exists( 'FCPAddPostType' ) ) {
    include_once $this->self_path . 'classes/add-post-type.class.php';
}

new FCPAddPostType( [
    'name' => 'Rechnungsdaten',
    'type' => 'billing',
    'slug' => 'rechnung',
    'plural' => 'Rechnungsdaten',
    'description' => 'The list of payment options',
    'fields' => ['title', 'author', 'revisions'],
    'hierarchical' => false,
    'public' => false,
    'gutenberg' => false,
    'menu_position' => 23,
    'menu_icon' => 'dashicons-money-alt',
    'has_archive' => false,
    'capability_type' => ['billing', 'billings']
] );


// meta fields for new post types on basis of the form structure

if ( !class_exists( 'FCP_Add_Meta_Boxes' ) ) {
    include_once $this->self_path . 'classes/add-meta-boxes.class.php';
}
if ( !class_exists( 'FCP_Forms__Draw' ) ) {
    include_once $this->self_path . 'classes/draw-fields.class.php';
}

$json = FCP_Forms::structure( $dir );
if ( $json === false ) { return; }

new FCP_Add_Meta_Boxes( $json, (object) [
    'title' => __( 'Billing Details', 'fcpfo' ), // Rechnungsdaten
    'post_types' => ['billing'],
    'context' => 'normal',
    'priority' => 'high'
] );

// style the wp-admin // ++move to main maybe? ++attach to particular pages
add_action( 'admin_enqueue_scripts', function() use ($dir) {
    wp_enqueue_script(
        'fcp-forms-billing-admin',
        $this->forms_url . $dir . '/scripts-admin.js',
        ['jquery'],
        $this->js_ver 
    );
});


// ****************************limit billing data to only one post status****************************
add_action( 'init', function() {
	register_post_status( 'active', [
		'label'                     => __( 'Active' ),
        'label_count'               => false,
        'exclude_from_search'       => true,
        '_builtin'                  => false,
        'public'                    => false,
        'internal'                  => true,
        'protected'                 => true,
        'private'                   => true,
        'publicly_queryable'        => false,
        'show_in_admin_status_list' => false,
        'show_in_admin_all_list'    => false,
	]);
});

// quick edit screen hide status
add_action( 'admin_footer-edit.php', function() {
    global $post;
    if( $post->post_type !== 'billing' ) { return; }
    ?>
    <style>
        .inline-edit-status { display:none!important }
    </style>
    <script>
    jQuery( document ).ready( function($) {
        $( 'select[name="_status"] option' ).remove();
        $( 'select[name="_status"]' ).append( '<option value="active" selected>Active</option>' );
    });
    </script>
    <?php
}, 0);

// post screen
add_action( 'admin_head', function() {
    if ( !in_array( $GLOBALS['pagenow'], [ 'post.php', 'post-new.php' ] ) ) { return; }
    if ( $GLOBALS['post_type'] !== 'billing' ) { return; }
    ?>
    <style>
        #minor-publishing { display:none }
    </style>
    <script>
    jQuery( document ).ready( function($) {
        $( '#minor-publishing' ).remove();
        $( '#publish' ).attr( 'name', 'save' );
        $( '#publish' ).val( '<?php _e( 'Save' ) ?>' );
    });
    </script>
    <?php
});

// change the save metabox headline
add_filter( 'do_meta_boxes', function() {
    global $wp_meta_boxes;
    $wp_meta_boxes['billing']['side']['core']['submitdiv']['title'] = __( 'Options' );
    return $wp_meta_boxes;
}, 0 );

// only one particular status is allowed to be saved
add_action( 'save_post_billing', 'fcp_forms_billing_unique_status', 10, 2 );
function fcp_forms_billing_unique_status($postID, $data) {
    if ( $data->post_status === 'trash' || $_POST['post_status'] === 'trash' ) { return; }
    if ( wp_is_post_revision( $postID ) ) { return; } // ++--not sure it is needed

    remove_action( 'save_post_billing', 'fcp_forms_billing_unique_status' );
    wp_update_post( wp_slash([
        'ID' => $_POST['ID'],
        'post_status' => 'active'
    ]));
    add_action( 'save_post_billing', 'fcp_forms_billing_unique_status' );
}
//*/
/* this method works, but with a lot of exceptions.. I suspect, these are not all, so avoided it here
add_filter( 'wp_insert_post_data', function($data) {
    if ( $GLOBALS['pagenow'] === 'post-new.php' ) { return $data; }
    //++can't trash
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
    if ( wp_is_post_revision( $post_id ) !== false ) { return; }
    if ( $data['post_type'] !== 'billing' ) { return; }

    $data['post_status'] = 'active';
    return $data;
}, 99 );
//*/