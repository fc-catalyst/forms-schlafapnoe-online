<?php

// add post types for clinics & doctors
if ( !class_exists( 'FCPAddPostType' ) ) {
    include_once $this->self_path . 'classes/add-post-type.class.php';
}

register_activation_hook( $this->self_path_file, function() {
    add_action( 'wp_loaded', function() {
        flush_rewrite_rules();
    });
});
register_deactivation_hook( $this->self_path_file, function() {
    flush_rewrite_rules();
});

new FCPAddPostType( [
    'name' => 'Clinic',
    'type' => 'clinic',
    'slug' => 'kliniken',
    'plural' => 'Clinics',
    'archives' => 'Clinics and Doctors',
    'description' => 'The list of clinics, registered by you',
    'fields' => ['title', 'comments', 'author'],
    'hierarchical' => false,
    'public' => true,
    'gutenberg' => false,
    'menu_position' => 21,
    'menu_icon' => 'dashicons-plus-alt',
    'has_archive' => true,
    'capability_type' => ['entity', 'entities'],
    'text_domain' => 'fcpfo-ea',
    'x-taxonomies' => [
        'name' => 'Field',
        'type' => 'field',
        'plural' => 'Fields',
        'description' => 'The list of categories for Clinics and Doctors',
        'public' => true, // ++turn to false in the end
        'hierarchical' => true,
        'show_admin_column' => false
    ]
]);

new FCPAddPostType( [
    'name' => 'Doctor',
    'type' => 'doctor',
    'slug' => 'aerzte',
    'plural' => 'Doctors',
    'description' => 'The list of doctors, registered by you',
    'fields' => ['title', 'comments', 'author'],
    'hierarchical' => false,
    'public' => true,
    'gutenberg' => false,
    'menu_position' => 22,
    'menu_icon' => 'dashicons-insert',
    'has_archive' => true,
    'capability_type' => ['entity', 'entities'],
    'text_domain' => 'fcpfo-ea',
    'x-taxonomies' => [ 'type' => 'field' ]
]);


// pages templates ++move the templates to the FCPADDPostType class
add_filter( 'template_include', function( $template ) {

    $new_template = $template; // default theme template
    $path = $this->forms_path . 'entity-add/templates/'; // ++get the dir name automatically for all

    if ( is_singular( 'clinic' ) || is_singular( 'doctor' ) ) {
        $new_template = $path . 'entity-template.php'; // ++rename these with not prefix
    }

    if ( is_post_type_archive( 'clinic' ) || is_post_type_archive( 'doctor' ) ) {
        $new_template = $path . 'entities-archive.php';
    }

    if ( file_exists( $new_template ) ) {
        return $new_template;
    }

    return $template;

}, 99 );

add_filter( 'comments_template', function( $template ) {
//print_r( $template );
//exit;
//return $template;
    $new_template = $template; // default theme template
    $path = $this->forms_path . 'entity-add/templates/';

    if ( is_singular( 'clinic' ) || is_singular( 'doctor' ) ) {
		$new_template = $path . 'entity-comments.php';
	}
	
    if ( file_exists( $new_template ) ) {
        return $new_template;
    }

    return $template;
}, 99 );


// style the wp-admin // it is not in fcp-forms.php as it might have more conditions to appear
add_action( 'admin_enqueue_scripts', function($hook) use ($dir) {

    if ( !in_array( $hook, ['post.php', 'post-new.php'] ) ) { return; }

    $screen = get_current_screen();
    if ( !isset( $screen ) || !is_object( $screen ) ) { return; }
    
    if ( !in_array( $screen->post_type, ['clinic', 'doctor'] ) ) { return; }

    wp_enqueue_script(
        'fcp-forms-entity-admin',
        $this->forms_url . $dir . '/scripts-admin.js',
        ['jquery'],
        $this->js_ver
    );
});


// add translation languages
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'fcpfo-ea', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});


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
    'title' => 'Unternehmensinformationen',
    'post_types' => ['clinic', 'doctor'],
    'context' => 'normal',
    'priority' => 'high'
] );


// disable yoast schema, as the types have their own markup //++disable another seo schema
add_filter( 'wpseo_json_ld_output', function() {
    if ( is_singular( 'clinic' ) || is_singular( 'doctor' ) ) {
        return false;
    }
});

// ++it is here, because I haven't found a better place for it yet
add_shortcode( 'fcp-get-to-print', function($atts = []) {

    $allowed = [
        '_get' => '',
        '_post' => '',
        'html' => '',
    ];

    $atts = shortcode_atts( $allowed, $atts ); // ++ add that modifying function of mine to change a="" to just a
    
    if ( $atts['_get'] && isset( $_GET[ $atts['_get'] ] ) && $atts['html'] ) {
        return $atts['html'];
    }
    
    if ( $atts['_post'] && isset( $_GET[ $atts['_post'] ] ) && $atts['html'] ) {
        return $atts['html'];
    }
});


// hide the images on trash and back
add_action( 'transition_post_status', function($new_status, $old_status, $post) {
    if ( !in_array( $post->post_type, ['clinic', 'doctor'] ) ) { return; }
    $upl = wp_get_upload_dir();
    $dir = $upl['basedir'] . '/entity/' . $post->ID;
    $url = $upl['url'] . '/entity/' . $post->ID;

    // restore
    if ( $old_status === 'trash' && $new_status !== 'trash' ) {
        rename( $dir . '_trashed', $dir );
    }

    // clear cloudflare cached images on trash
    if ( $old_status !== 'trash' && $new_status === 'trash' ) {
        if ( class_exists( '\CF\WordPress\HooksByURL' ) ) {
            $imgs = [];
            $get_urls = function($d) use (&$get_urls, &$imgs, $dir, $url) {
                if ( !is_dir( $d ) ) { return; }
                $files = array_diff( scandir( $d ), ['.', '..'] );
                foreach ( $files as $file ) {
                    if ( is_dir( $d . '/' . $file ) ) {
                        $get_urls( $d . '/' . $file );
                        continue;
                    }
                    if ( in_array( pathinfo( $file, PATHINFO_EXTENSION ), ['jpg', 'jpeg', 'png', 'gif'] ) ) {
                        $imgs[] = str_replace( $dir, $url, $d ) . '/' . $file;
                    }
                }
            };
            $get_urls( $dir );

            if ( !empty( $imgs ) ) {
                $cf = new \CF\WordPress\HooksByURL();
                $cf->purgeCacheByRelevantURLsTRUE( $imgs );
            }
        }
    }

    // trash
    if ( $old_status !== 'trash' && $new_status === 'trash' ) {
        rename( $dir, $dir . '_trashed' );
    }

}, 10, 3 );

// delete images on delete
add_action( 'delete_post', function($postID) {

    if ( !in_array( get_post_type( $postID ), ['clinic', 'doctor'] ) ) { return; }

    $dir = wp_get_upload_dir()['basedir'] . '/entity/' . $postID;
    $rmdir = function($dir) use (&$rmdir) {
        if ( !is_dir( $dir ) ) { return; }
        $files = array_diff( scandir( $dir ), ['.', '..'] );
        foreach ( $files as $file ) {
            if ( is_dir( $dir . '/' . $file ) ) {
                $rmdir( $dir . '/' . $file );
                continue;
            }
            unlink( $dir . '/' . $file );
        }
        rmdir( $dir );
    };
    $rmdir( $dir . '_trashed' );
    $rmdir( $dir );
});


// rank math breadcrumbs fix
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class ) {
    foreach ( $crumbs as &$v ) {
        if ( $v[0] === 'Klinik' ) {
            $v[0] = 'Kliniken und Ärzte'; // ++or get the title dynamically
        }
        if ( $v[0] === 'Arzt' ) {
            $v[0] = 'Kliniken und Ärzte';
            $v[1] = home_url( '/kliniken/' );
        }
    }
	return $crumbs;
}, 10, 2);

//*
// notify the moderator about the new entity posted for review
add_action( 'transition_post_status', function($new_status, $old_status, $post) {
    if ( !in_array( $post->post_type, ['clinic', 'doctor'] ) ) { return; }
    if ( $new_status !== 'pending' || !in_array( $old_status, ['new', 'draft', 'auto-draft'] ) ) { return; }
    require_once __DIR__ . '/../../mail/mail.php';
    FCP_FormsMail::to_moderator( 'entity_added', $post->ID );
}, 10, 3 );

// notify the client, that the new entity was published
add_action( 'transition_post_status', function($new_status, $old_status, $post) {
    if ( !in_array( $post->post_type, ['clinic', 'doctor'] ) ) { return; }
    if ( $old_status === 'publish' || $new_status !== 'publish' ) { return; }
    require_once __DIR__ . '/../../mail/mail.php';
    FCP_FormsMail::to_user( 'published', $post->ID );
}, 10, 3 );
//*/

// hide the ping-back checkbox from admins and all
add_action( 'admin_footer', function() {
    global $post;
    if ( !in_array( $post->post_type, [ 'clinic', 'doctor' ] ) ) { return; }
    ?>
    <style>
        label[for=comment_status] ~ * { display:none; }
    </style>
    <?php
});

// the cloudflare plugin clear by url, made for images, which remain even after hidding
add_action( 'plugins_loaded', function() {
    if ( !is_admin() ) { return; }
    if ( !class_exists( '\CF\WordPress\Hooks' ) ) { return; }
    require_once( __DIR__ . '/inc/cloudflare_extend.php' );
});


// combine the meta fields into the content
function fcp_forms_ea_fill_in_the_content( $post_id ) {

    $post_type = get_post_type( $post_id );
    if ( !in_array( $post_type, ['clinic', 'doctor'] ) ) { return; }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }

    remove_action( 'save_post', 'fcp_forms_ea_fill_in_the_content', 20 );

    $meta_keys = array(
        'entity-phone' => '',
        'entity-address' => '',
        'entity-specialty' => '',
        'entity-tags' => '',
        'entity-content' => '',
    );

    foreach ( $meta_keys as $meta_key => &$meta_value ) {
        $meta_value = get_post_meta( $post_id, $meta_key, true );
    }

    $content = implode( "\n\n", $meta_keys );

    wp_update_post( array(
        'ID' => $post_id,
        'post_content' => $content
    ) );

    add_action( 'save_post', 'fcp_forms_ea_fill_in_the_content', 20 );
}
add_action( 'save_post', 'fcp_forms_ea_fill_in_the_content', 20 );



// find closest locations by provided coordinates
add_action( 'rest_api_init', function () { // it is in entity-add to easier include the template functions.php

    $args = [
        'methods'  => 'GET',
        'callback' => function( WP_REST_Request $r ) {

            $range = 100; // km
            
            $km2deg = function($a, $lat = 0, $lng = 0) { // 1 deg = ~111 km
                return round( $a / 111, 2 );
            };
            $deg2km = function($a, $lat = 0, $lng = 0) {
                return round( $a * 111, 1 );
            };
            // ++can use a better formula or even a distance api in future
            // https://stackoverflow.com/questions/1253499/simple-calculations-for-working-with-lat-lon-and-km-distance
            
            $esc = function($a) {
                global $wpdb;
                return $wpdb->_real_escape( htmlspecialchars( urldecode( $a ) ) );
            };

            global $wpdb;

            // select ids and range of the nearest fitting entities
            $near = $wpdb->get_results( '
            SELECT
                posts.ID,
                SQRT( POWER( CAST( mt1.meta_value AS FLOAT ) - '.$esc( $r['lat'] ).', 2 )
                    + POWER( CAST( mt2.meta_value AS FLOAT ) - '.$esc( $r['lng'] ).', 2 ) )
                    AS distance
            FROM `'.$wpdb->posts.'` AS posts
                LEFT JOIN `'.$wpdb->postmeta.'` AS mt0 ON ( posts.ID = mt0.post_id )
                LEFT JOIN `'.$wpdb->postmeta.'` AS mt1 ON ( posts.ID = mt1.post_id )
                LEFT JOIN `'.$wpdb->postmeta.'` AS mt2 ON ( posts.ID = mt2.post_id )
            WHERE
                (
                    ( mt0.meta_key = "entity-specialty" AND mt0.meta_value = "'.$esc( $r['spc'] ).'" )
                    AND
                    ( mt1.meta_key = "entity-geo-lat" AND mt1.meta_value <> "" )
                    AND
                    ( mt2.meta_key = "entity-geo-long" AND mt2.meta_value <> "" )
                )
                AND # exclude the initially picked by id, instead of adding the city / region / index
                posts.ID NOT IN (0'.( empty( $r['exc'] ) ? '' : ','.$esc( $r['exc'] ) ).')
                AND
                posts.post_type IN ("clinic", "doctor")
                AND
                posts.post_status IN ("publish", "private")
            GROUP BY posts.ID
            HAVING distance < '.$esc( $km2deg( $range ) ).'
            ORDER BY distance ASC
            LIMIT 0, 12
            ');

            if ( empty( $near ) ) {
                return new WP_Error( 'no_data_found', 'No Data Found', [ 'status' => 404 ] );
            }

            // ++ add caching https://wp-kama.ru/function/nocache_headers

            $ids = []; $ranges = [];
            foreach ( $near as $v ) {
                $ids[] = $v->ID;
                $ranges[ $v->ID ] = $deg2km( $v->distance );
            }

            // select the tiles
            $args = [
                'post_type'        => ['clinic', 'doctor'],
                'post__in'         => $ids,
                'orderby'          => 'post__in',
                'posts_per_page'   => count( $ids ),
                'paged'            => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
                'post_status'      => ['publish', 'private'],
            ];

            $wp_query = new WP_Query( $args );

            if ( $wp_query->have_posts() ) {

                include_once ( __DIR__ . '/templates/functions.php' );

                ob_start();
                while ( $wp_query->have_posts() ) {
                    $wp_query->the_post();
                    
                    fcpf\eat\entity_tile_print( sprintf( 
                        __( 'Within %s km radius', 'fcpfo-ea' ),
                        $ranges[ get_the_ID() ]
                    ));
                }
                $content = ob_get_contents();
                ob_end_clean();

                //wp_reset_query();

                return new WP_REST_Response( (object) [
                    'content' => $content,
                ], 200 );

            } else {

                return new WP_Error( 'no_data_found', 'No Data Found', [ 'status' => 404 ] );
            }


        },
        'permission_callback' => function() { // just a debugging rake
            if ( empty( $_SERVER['HTTP_REFERER'] ) ) { return false; }
            if ( strtolower( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) ) !== strtolower( $_SERVER['HTTP_HOST'] ) ) { return false; }
            return true;
        },
    ];

    register_rest_route( 'fcp-forms/v1', '/entities_around/(?P<lat>\d{1,3}\.?\d*)/(?P<lng>\d{1,3}\.?\d*)/(?P<spc>.+)/(?P<exc>[\d\,]+)', $args );
    register_rest_route( 'fcp-forms/v1', '/entities_around/(?P<lat>\d{1,3}\.?\d*)/(?P<lng>\d{1,3}\.?\d*)/(?P<spc>.+)', $args );
    // ++add var with no specialty
});

// common search results by the post type
add_action( 'rest_api_init', function () {

    $route_args = [
        'methods'  => 'GET',
        'callback' => function( \WP_REST_Request $request ) use ( $wp_query_args ) {

            $wp_query_args = [
                //'post_type' => ['clinic', 'doctor'],
                'post_status' => 'publish',
                //'sentence' => true,
                'posts_per_page' => 20,
                //'orderby' => 'relevance',
                'orderby'=> ['type'=>'ASC', 'relevance'=>'ASC'],
                //'order'   => 'ASC',
            ];
            if ( !empty( $request['exclude_ips'] ) ) {
                $exclude_ids = explode( ',', trim( $request['exclude_ips'], ',' ) );
                $wp_query_args['post__not_in'] = $exclude_ids;
            }

            $wp_query_args['s'] = $request['search'];

            $search = new \WP_Query( $wp_query_args );

            if ( !$search->have_posts() ) {
                new \WP_Error( 'nothing_found', 'No results found', [ 'status' => 404 ] );
            }

            if ( $search->have_posts() ) {

                //include_once ( __DIR__ . '/templates/functions.php' );

                ob_start();
                while ( $search->have_posts() ) {
                    $search->the_post();
                    //fcpf\eat\entity_tile_print();
                    //echo get_the_ID() . ' ';
                    get_template_part( 'template-parts/search', 'row' );
                }
                $content = ob_get_contents();
                ob_end_clean();

                //wp_reset_query();

                return new WP_REST_Response( (object) [
                    'content' => $content,
                ], 200 );

            } else {

                return new WP_Error( 'no_data_found', 'No Data Found', [ 'status' => 404 ] );
            }
        },
        'permission_callback' => function() {
            if ( empty( $_SERVER['HTTP_REFERER'] ) ) { return false; }
            if ( strtolower( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) ) !== strtolower( $_SERVER['HTTP_HOST'] ) ) { return false; }
            return true;
        },
        'args' => [
            'search' => [
                'description' => 'The search query',
                'type'        => 'string',
                'validate_callback' => function($param) {
                    return trim( $param ) ? true : false;
                },
                'sanitize_settings' => function($param, $request, $key) {
                    return sanitize_text_field( urldecode( $param ) ); // return htmlspecialchars( wp_unslash( urldecode( $param ) ) );
                },
            ],
        ],
    ];

    register_rest_route( 'fcp-forms/v1', '/entities_search/(?P<search>.{1,90})/(?P<exclude_ips>[\d\,]+)', $route_args );
    register_rest_route( 'fcp-forms/v1', '/entities_search/(?P<search>.{1,90})', $route_args );
});