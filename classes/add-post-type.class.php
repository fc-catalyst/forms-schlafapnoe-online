<?php

class FCPAddPostType {

    private $p;

    public static function version() {
        return '2.1.1';
    }

    public function __construct($p) {

        $this->p = $p; // post type prefs

        add_action( 'init', [ $this, 'addPostType' ] );

        if ( $p['gutenberg'] && $p['gutenberg_allow'] ) {
            add_filter( 'allowed_block_types', [ $this, 'limitGutenberg' ], 10, 2 );
        }

    }

    public function addPostType() {

        $p = $this->p;
        $td = empty( $p['text_domain'] ) ? 'fcpfo' : $p['text_domain'];
    
        $labels = [
            'name'                => __( $p['plural'], $td ),
            'singular_name'       => __( $p['name'], $td ),
            'menu_name'           => __( $p['plural'], $td ),
            'all_items'           => __( 'View All ' . $p['plural'], $td ),
            'archives'            => __( isset( $p['archives'] ) ? $p['archives'] : 'All ' . $p['plural'], $td ),
            'view_item'           => __( 'View ' . $p['name'], $td ),
            'add_new'             => __( 'Add New', $td ),
            'add_new_item'        => __( 'Add New ' . $p['name'], $td ),
            'edit_item'           => __( 'Edit ' . $p['name'], $td ),
            'update_item'         => __( 'Update ' . $p['name'], $td ),
            'search_items'        => __( 'Search ' . $p['name'], $td ),
            'not_found'           => __( $p['name'] . ' Not Found', $td ),
            'not_found_in_trash'  => __( $p['name'] . ' Not found in Trash', $td ),
        ];
            
        $args = [
            'label'               => __( $p['name'], $td ),
            'description'         => __( $p['description'], $td ),
            'labels'              => $labels,
            'supports'            => $p['fields'],
            'hierarchical'        => $p['hierarchical'],
            'public'              => $p['public'],
            'show_in_rest'        => $p['gutenberg'],
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => true,
            'menu_position'       => $p['menu_position'],
            'menu_icon'           => $p['menu_icon'],
            'can_export'          => true,
            'has_archive'         => $p['has_archive'],
            'exclude_from_search' => $p['public'] ? false : true,
            'publicly_queryable'  => $p['public'],
            'capability_type'     => $p['capability_type'] ? $p['capability_type'] : 'page',
            'map_meta_cap'        => $p['capability_type'] || $p['capability'] ? true : null,
        ];
        if ( $p['slug'] ) {
            $args['rewrite'] = [ 'slug' => $p['slug'] ];
        }
        
        if ( !isset( $p['taxonomies'] ) ) {
            register_post_type( $p['type'], $args );
            return;
        }

        // custom taxonomies
        $t = $p['taxonomies'];

        //++foreach
        if ( !taxonomy_exists( $t['type'] ) ) {
            //++improve all the params according to modern
            $taxlabels = [
                'name'                => __( $t['plural'], $td ),
                'singular_name'       => __( $t['name'], $td ),
                'menu_name'           => __( $t['plural'], $td ),
                'all_items'           => __( 'All ' . $t['plural'], $td ),
                'view_item'           => __( 'View ' . $t['name'], $td ),
                'add_new_item'        => __( 'Add New ' . $t['name'], $td ),
                'edit_item'           => __( 'Edit ' . $t['name'], $td ),
                'update_item'         => __( 'Update ' . $t['name'], $td ),
                'search_items'        => __( 'Search ' . $t['name'], $td ),
                'parent_item'         => __( 'Parent ' . $t['plural'], $td ),
                'parent_item_colon'   => __( 'Parent ' . $t['plural'], $td ) . ':',
                'new_item_name'       => __( 'New ' . $t['name'] . ' title', $td ),
                'back_to_items'       => __( '← Back to ' . $t['name'], $td ),
            ];
        
            $taxargs = [
                'label'               => __( $t['name'], $td ),
                'labels'              => $taxlabels,
                'description'         => __( $t['description'], $td ),
                'public'              => $t['public'],
                'hierarchical'        => $t['hierarchical'],
                'show_admin_column'   => $t['show_admin_column'],
            ];
 
            register_taxonomy( $t['type'], $p['type'], $taxargs );

        }

        if ( $p['taxonomies'] ) {
            $args['taxonomies'] = [ $t['type'] ];
        }

        register_post_type( $p['type'], $args );

    }
    
    public function limitGutenberg( $allowed_blocks ) {
    
        global $post;
        $p = $this->p;

        if ( $post->post_type !== $p['type'] || !$p['gutenberg_allow'] ) {
            return $allowed_blocks;
        }

        return $p['gutenberg_allow'];
    }

}
