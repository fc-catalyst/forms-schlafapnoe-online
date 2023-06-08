<?php

/*
Plugin Name: FCP Forms Engine
Description: JSON structured forms engine with pre-made forms examples.
Version: 1.0.3
Requires at least: 4.7
Requires PHP: 7.0.0
Author: Firmcatalyst, Vadim Volkov
Author URI: https://firmcatalyst.com
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: fcpfo
Domain Path: /languages
*/

defined( 'ABSPATH' ) || exit;

class FCP_Forms {

	public static $dev = false,
                  $tmp_dir = 'fcp-forms-tmps',
                  $text_domain = 'fcpfo', // ++ delete or use
                  $prefix = 'fcpf',
                  $tz = ''; // timezone
                  
    private $forms = [],
            $form_tab = [];
	
	private function plugin_setup() {

		$this->self_url  = plugins_url( '/', __FILE__ ); // ++to constants or even to a separate include
		$this->self_path = plugin_dir_path( __FILE__ );
		$this->self_path_file = __FILE__;

		$this->forms_url  = $this->self_url . 'forms/';
		$this->forms_path = $this->self_path . 'forms/';
		
		$this->assets = $this->self_url . 'assets/';

		$this->css_ver = '1.0.9' . ( self::$dev ? '.'.time() : '' );
		$this->js_ver = '1.1.8' . ( self::$dev ? '.'.time() : '' );
		$this->css_adm_ver = '0.0.3' . ( self::$dev ? '.'.time() : '' );
		$this->js_adm_ver = '0.0.8' . ( self::$dev ? '.'.time() : '' );

	}

    public function __construct() {

        $this->plugin_setup();

        add_shortcode( 'fcp-form', [ $this, 'add_shortcode' ] );
        add_shortcode( 'fcp-forms-tabs', [ $this, 'add_shortcode_tabs' ] );
        add_action( 'template_redirect', [ $this, 'process' ] );
        
        register_activation_hook( __FILE__, [ $this, 'install' ] );
        register_deactivation_hook( __FILE__, [ $this, 'uninstall' ] );

        // initial forms settings, which must have even without the form on the page
        $this->forms = array_values( array_diff( scandir( $this->forms_path ), [ '.', '..' ] ) );
        foreach ( $this->forms as $dir ) {
            if ( $dir[0] === '-' ) { continue; } // dirs, starting with '-' are skipped // ++add to other parts
            @include_once $this->forms_path . $dir . '/' . 'index.php';
        }

        // allow js track the helpers' urls
        add_action( 'wp_head', function() {
            echo '<script>window.fcp_forms_assets_url="' . $this->assets .'";window.fcp_forms_data={};</script>'."\n";
        }, 8);

        add_action( 'wp_enqueue_scripts', function() {
            wp_enqueue_script( 'fcp-forms', $this->self_url . 'scripts.js', ['jquery'], $this->js_ver, true );
        });

        // load styles (layout, common, private styles) ++move to a separate function ++separate to track shortcode & styles & some other
        add_action( 'wp_head', function() {
            
            if ( is_home() || is_archive() ) { return; }
            
            global $post;
            if ( !$post->post_content || strpos( $post->post_content, '[fcp-form' ) === false ) { return; }
            
            preg_match_all(
                '/\[fcp\-form(?:s\-tabs)?(\s+[^\]]+)\]/i',
                $post->post_content, $matches, PREG_SET_ORDER
            );

            $first_screen = [];
            $second_screen = [];

            foreach ( $matches as $v ) { // get dir name / dirs' names
            
                preg_match( '/\s+dirs?=([^\s]+)\s?/i', $v[1], $matches1 );
                
                $dirs = array_map( 'trim', explode( ',', trim( $matches1[1], '\'"') ) );

                foreach ( $dirs as $dir ) {
                
                    if ( strpos( $v[1], 'firstscreen' ) !== false ) {
                        $first_screen[] = $dir;
                        continue;
                    }
                    
                    $second_screen[] = $dir;
                }

            }

            $first_screen = array_values( array_unique( $first_screen ) );
            $second_screen = array_values( array_unique( $second_screen ) );

            if ( !isset( $first_screen[0] ) && !isset( $second_screen[0] ) ) { return; }

            // if a form has its own style.css - don't use main style.css at all; ++maybe add attr or an option to avoid
            // always use layout (if a form is found), just vary if on first screen or second.

            // first screen styles

            $default_layout_first_loaded = false;
            $default_style_first_loaded = false;

            if ( isset( $first_screen[0] ) ) {
                echo '<style>';

                // main layout load
                echo "\n\n".'/*---------- main layout.css ----------*'.'/'."\n";
                @include_once $this->self_path . 'layout.css'; // main layout goes firstscreen, if at least one is fs
                $default_layout_first_loaded = true;

                // main style load if a form has no own style
                foreach ( $first_screen as $dir ) {
                    if ( is_file( $this->forms_path . $dir . '/' . 'style.css' ) ) { continue; }
                    
                    echo "\n\n".'/*---------- ' . $dir . ' <- main style.css ----------*'.'/'."\n";
                    @include_once $this->self_path . 'style.css';
                    $default_style_first_loaded = true;
                    break;
                }

                // private styles load
                foreach ( $first_screen as $dir ) {
                    if ( !is_file( $this->forms_path . $dir . '/' . 'style.css' ) ) { continue; }

                    echo "\n\n".'/*---------- '.$dir.'/style.css ----------*'.'/'."\n";
                    include_once $this->forms_path . $dir . '/' . 'style.css';
                }

                echo '</style>';
            }


            // not first screen styles
            if ( !isset( $second_screen[0] ) ) { return; }

            $styles_depend_on = [];
            
            // main layout load
            if ( !$default_layout_first_loaded ) {
                wp_enqueue_style(
                    'fcp-forms--layout',
                    $this->self_url . 'layout.css',
                    [],
                    $this->css_ver
                );
                $styles_depend_on[] = 'fcp-forms--layout';
            }

            // main style load if a form has no own style
            if ( !$default_style_first_loaded ) {
                foreach ( $second_screen as $dir ) {
                    if ( is_file( $this->forms_path . $dir . '/' . 'style.css' ) ) { continue; }

                    wp_enqueue_style(
                        'fcp-forms--style',
                        $this->self_url . 'style.css',
                        $styles_depend_on,
                        $this->css_ver
                    );
                    $styles_depend_on[] = 'fcp-forms--style';
                    break;
                }
            }
            
            // private styles load
            foreach ( $second_screen as $dir ) {
                if ( !is_file( $this->forms_path . $dir . '/' . 'style.css' ) ) { continue; }

                wp_enqueue_style(
                    'fcp-forms-' . $dir,
                    $this->forms_url . $dir . '/style.css',
                    $styles_depend_on,
                    $this->css_ver
                );
            }
            
        }, 8);

        
        // admin part // make the layout base work
        add_action( 'admin_enqueue_scripts', [ $this, 'add_styles_scripts_admin' ] );
        
        // admin form allow uploading ++move to particular indexes
        add_action( 'post_edit_form_tag', function() {
            echo 'enctype="multipart/form-data"';
        });
        
        // remove h1 & h2 from tinymce
        add_filter( 'tiny_mce_before_init', function($args) { // ++specify
            $args['block_formats'] = 'Paragraph=p;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Pre=pre';
            return $args;
        });
        
        // add translation languages
        add_action( 'plugins_loaded', function() {
            // fcp-forms/languages
            load_plugin_textdomain( 'fcpfo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        });
        
        // Really Simple Captcha async loading to override cloudflare hard caching
        add_action( 'rest_api_init', function () {

            $args = [
                'methods'  => 'GET',
                'callback' => function( WP_REST_Request $request ) {
                    if ( !class_exists( 'ReallySimpleCaptcha' ) ) {
                        return new WP_Error(
                            'rscaptcha_not_active',
                            'Really Simple Captcha plugin not active',
                            [ 'status' => 404 ]
                        );
                    }

                    // get the rscaptcha field
                    ob_start();
                    $json = FCP_Forms::structure( 'delegate-login' );
                    if ( $json === false ) { return; }
                    $json = FCP_Forms::flatten( $json->fields );
                    foreach ( $json as $v ) {
                        if ( $v->type !== 'rscaptcha' ) { continue; }
                        unset( $v->async );
                        FCP_Forms__Draw::rscaptcha_print( $v );
                    }

                    $content = ob_get_contents();
                    ob_end_clean();
                    
                    //return new WP_REST_Response( true, 200 );
                    $result = new WP_REST_Response( (object) [
                        'content' => FCP_Forms__Draw::align_html_codes( $content ),
                    ], 200 );

                    //$result->set_headers( ['Cache-Control' => 'no-cache'] ); // ++make dependent on the structure json
                    //wp_get_nocache_headers(); ++ maybe use those, instead of just set headers
                    nocache_headers();

                    return $result;
                },
                'permission_callback' => function() { // just a debugging rake
                    if ( empty( $_SERVER['HTTP_REFERER'] ) ) { return false; }
                    if ( strtolower( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) ) !== strtolower( $_SERVER['HTTP_HOST'] ) ) { return false; }
                    return true;
                },
            ];

            register_rest_route( 'fcp-forms/v1', '/rscaptcha', $args );
            register_rest_route( 'fcp-forms/v1', '/rscaptcha/(?P<c>[\d\w]+)', $args );
        });

    }
    
    public function install() {
    
        // create tmp dir for the files
        $dir = wp_get_upload_dir()['basedir'];
        mkdir( $dir . '/' . self::$tmp_dir );

        // create unique plugin id
        file_put_contents(
            self::plugin_unid_path(),
            '<?php return "' . md5( time() ) . '";'
        );

    }
    
    public function uninstall() {
    
        // remove tmp dir
        include_once $this->self_path . 'classes/files.class.php';
        $dir = wp_get_upload_dir()['basedir'];
        FCP_Forms__Files::rm_dir( $dir . '/' . self::$tmp_dir );
        
        // remove unique plugin id
        unlink( self::plugin_unid_path() );

    }
    
    public function process() { // when $_POST is passed by the client side

        if ( empty( $_POST['fcp-form-name'] ) ) { // handle only the fcp-forms
            return;
        }
        $form_name = $_POST['fcp-form-name'];
        $nonce = $_POST[ 'fcp-form--' . $form_name ];

        if ( isset( $_FILES ) ) {
            include_once $this->self_path . 'classes/files.class.php';
        }
        include_once $this->self_path . 'classes/validate.class.php';

        // only allowed symbols for the form name
        if ( FCP_Forms__Validate::name( true, $form_name ) ) {
            return;
        }

        // common wp nonce check for logged in users
        if (
            !isset( $nonce ) ||
            !wp_verify_nonce( $nonce, FCP_Forms::plugin_unid() )
        ) {
            return;
        }

        // if the form doesn't exist
        $json = self::structure( $form_name );
        if ( $json === false ) { return; }

        $warns = new FCP_Forms__Validate( $json, $_POST, $_FILES );
        
        // get the array of wrong filled fields' warnings
        if ( !empty( $warns->result ) ) {
            $warning = __( 'Some fields are not filled correctly', 'fcpfo' );
        }

        // prepare the list of files to process
        if ( isset( $_FILES ) ) {
            $uploads = new FCP_Forms__Files( $json, $_FILES, $warns->files_failed );
        }

        // main processing
        @include_once( $this->forms_path . $form_name . '/process.php' );

        // failure
        if ( isset( $warning ) || !empty( $warns->result ) ) {
            $_POST['fcp-form--'.$form_name.'--warning'] = $warning; // passing to printing hook via globals
            $_POST['fcp-form--'.$form_name.'--warnings'] = $warns->result;
            return;
        }

        // success

        if ( isset( $_POST['fcp--redirect'] ) ) { // process.php $redirect overrides by $atts['redirect']
            $redirect = $_POST['fcp--redirect'];
        }

        if ( isset( $redirect ) ) {
            wp_redirect( $redirect );
            exit;
        }

        wp_redirect( $_POST['_wp_http_referer'] ? $_POST['_wp_http_referer'] : get_permalink() );
        exit;

	}
    
	public function add_shortcode($atts = []) {

        $allowed = [
			'dir' => '',
			'ignore_hide_on_GET' => false,
			'notcontent' => false, // if shortcode is rendered not from page-content
			'firstscreen' => false,
			'title' => false,
			'title_tag' => false,
			'override' => true,
			'redirect' => false,
		];
		$atts = $this->fix_shortcode_atts( $allowed, $atts );
		$atts = shortcode_atts( $allowed, $atts );

		return $this->add_shortcode_main( $atts );

	}
	
	public function add_shortcode_tabs($atts = []) {

        $allowed = [
			'dirs' => '',
			'tabs' => '',
			'notcontent' => false, // if shortcode is rendered not from page-content
			'firstscreen' => false,
			'title' => false,
			'title_tag' => false,
			'override' => true,
			'redirect' => false,
		];
		$atts = $this->fix_shortcode_atts( $allowed, $atts );
		$atts = shortcode_atts( $allowed, $atts );

		$dirs = array_map( 'trim', explode( ',', $atts['dirs'] ) );
		$tabs = array_map( 'trim', explode( ',', $atts['tabs'] ) );
		
		if ( !$dirs[1] ) { // no tabs for single form
            $this->add_shortcode_main( ['dir' => $dirs[0]] + $atts );
		}

		$inputs = '';
		$labels = [];
		$forms = '';
		$at = 0; // active tab

		foreach( $dirs as $k => $dir ) {
            $form = $this->add_shortcode_main( ['dir' => $dir] + $atts );
            if ( !$form ) { continue; }
            
            $labels[] = !empty( $tabs[ $k ] ) ? $tabs[ $k ] : 
                ( $this->form_tab[ $dir ] ? $this->form_tab[ $dir ] : $dir );
            $forms .= $form;
            if ( !empty( $_POST['fcp-form-name'] ) && $dir === $_POST['fcp-form-name'] ) {
                $at = array_key_last( $labels );
            }
        }
        
        if ( !$labels[0] ) { return; }
        
        if ( !$labels[1] ) {
            return $forms;
        }

        $unique_group = substr( md5( time() ), 0, 5 );
        foreach ( $labels as $k => &$v ) {
            $name = 'fcp-forms--tab-' . $unique_group;
            $id = $name . '-' . $k;
            
            $inputs .= '<input type="radio" name="'.$name.'" id="'.$id.'"'.( $k == $at ? ' checked' : '' ).'>';
            $v = '<label for="'.$id.'">' . $v . '</label>';
        }
        return '<div class="fcp-forms--tabbed">' .
                    $inputs .
                    '<div class="fcp-forms--tabs">' . implode( '', $labels ) . '</div>' .
                    '<div class="fcp-forms--forms">' . $forms . '</div>' .
               '</div>';

	}
	
	private function add_shortcode_main($atts) {

        if ( !$atts['dir'] || !self::form_exists( $atts['dir'] ) ) { return; }

        // add custom script from the form's dir
        if ( is_file( $this->forms_path . $atts['dir'] . '/scripts.js' ) ) { // ++doesn't work for reusable blocks
            wp_enqueue_script(
                'fcp-forms-' . $atts['dir'],
                $this->forms_url . $atts['dir'] . '/scripts.js',
                [ 'jquery', 'fcp-forms' ],
                $this->js_ver,
                true
            );
        }
        
        if ( $atts['notcontent'] ) { // ++this is fast solution for single form - improve!!
            wp_enqueue_style(
                'fcp-forms--layout',
                $this->self_url . 'layout.css',
                [],
                $this->css_ver
            );
            wp_enqueue_style(
                'fcp-forms--style',
                $this->self_url . 'style.css',
                ['fcp-forms--layout'],
                $this->css_ver
            );
            if ( is_file( $this->forms_path . $atts['dir'] . '/style.css' ) ) {
                wp_enqueue_style(
                    'fcp-forms-'.$atts['dir'],
                    $this->forms_url . $atts['dir'] . '/style.css',
                    ['fcp-forms--layout', 'fcp-forms--style'],
                    $this->css_ver
                );
            }
        }

        @include_once $this->forms_path . $atts['dir'] . '/' . 'if-shortcode.php'; // like, create a js data array or style some fields with conditions

        return $this->generate_form( $atts );
	}

	private function fix_shortcode_atts($allowed, $atts) { // turns isset to = true and fixes the default lowercase
        foreach ( $allowed as $k => $v ) {
            $l = strtolower( $k );
            if ( isset( $atts[ $l ] ) && $atts[ $l ] === 'false' ) { // 'false' value to boolean
                $atts[ $l ] = false;
            }
            if ( isset( $atts[ $l ] ) && $atts[ $l ] && !$atts[ $k ] ) { // fix the default atts lowercase attr key
                $atts[ $k ] = $atts[ $l ];
                unset( $atts[ $l ] );
                continue;
            }
            if ( in_array( $k, $atts ) ) { // isset attr true to value true ([override] => false [0] => firstscreen)
                $m = array_search( $k, $atts );
                if ( is_numeric( $m ) ) {
                    $atts[ $k ] = true;
                    unset( $atts[ $m ] );
                }
            }
        }
        return $atts;
	}

    public function add_styles_scripts_admin($hook) {

        if ( !in_array( $hook, ['post.php', 'post-new.php'] ) ) { return; }

        $screen = get_current_screen();
        if ( !isset( $screen ) || !is_object( $screen ) ) { return; }

        wp_enqueue_style( 'fcp-forms-layout', $this->self_url . 'layout.css', [], $this->css_ver );
        wp_enqueue_script( 'fcp-forms', $this->self_url . 'scripts.js', ['jquery'], $this->js_ver );

    }
	
	private function generate_form( $atts ) {

        $dir = $atts['dir'];
        $json = self::structure( $dir );
        if ( $json === false ) { return; }

        $this->form_tab[ $dir ] = isset( $json->options->tab ) ? $json->options->tab : '';

        // hide if $_GET
        if ( isset( $json->options->hide_on_GET ) && !isset( $atts['ignore_hide_on_GET'] ) ) { // ++to atts
            foreach ( (array) $json->options->hide_on_GET as $k => $v ) {

                if ( !is_array( $v ) ) {
                    $v = [ $v ];
                }

                // isset( $_GET[ $k ] ) -> hide
                if ( isset( $_GET[ $k ] ) && in_array( true, $v, true ) ) { return; }
                // !isset( $_GET[ $k ] ) -> hide
                if ( !isset( $_GET[ $k ] ) && in_array( false, $v, true ) ) { return; }
                // $_GET[ $k ] == $v -> hide
                if ( !empty( $_GET[ $k ] ) && in_array( $_GET[ $k ], $v ) ) { return; }

            }
        }

        // custom handler ++ can try to place it before fetching json?
        if ( $atts['override'] !== false ) { // contains a variable name to process in override.php
            @include_once( $this->forms_path . $dir . '/override.php' );
            if ( isset( $override ) ) {
                return $override;
            }
        }

        // overriding json with shortcode atts
        if ( isset( $json->fields[0]->gtype ) && $json->fields[0]->gtype === 'section' ) {
            $json->fields[0]->title = isset( $atts['title'] ) && $atts['title'] !== false ? $atts['title'] : ( isset( $json->fields[0]->title ) ? $json->fields[0]->title : '' );
            $json->fields[0]->title_tag = isset( $atts['title_tag'] ) && $atts['title_tag'] !== false ? $atts['title_tag'] : ( isset( $json->fields[0]->title_tag ) ? $json->fields[0]->title_tag : '' );
        }
        if ( $atts['redirect'] ) {
            $json->fields[] = (object) [
                'type' => 'hidden',
                'name' => 'fcp--redirect',
                'value' => $atts['redirect']
            ];
        }

        if ( $json->options->print_method == 'client' ) { // ++ not ready yet
            return '<form class="fcp-form" data-structure="'.$dir.'">' . __( 'Loading…', 'fcpfo' ) . '</form>';
        }

        include_once $this->self_path . 'classes/draw-fields.class.php';

        $draw = new FCP_Forms__Draw( $json, $_POST, $_FILES );
        return $draw->result;

	}

// -----______---___---_____HELPING FUNCITONS______---____--___


    public static function flatten($f, &$return = []) {
        foreach ( $f as $add ) {

            if ( isset( $add->type ) && $add->type ) {
                $return[] = $add;
                continue;
            }

            if ( $add->gtype ) {
                self::flatten( $add->fields, $return );
            }

        }
        return $return;
    }

    public static function unique($match = '', $length = 9) {
        $rnds = [ md5( rand() ), uniqid() ];

        $crop = array_map( function($v) use ($length) {
            return substr( $v, 0 - ceil( $length / 2 ) );
        }, $rnds );
        
        if ( $match ) {
            return preg_match( '/^[0-9a-f]{'.$length.'}$/', $match );
        }
        return substr( implode( '', $crop ), 0 - $length );
    }
    
    public static function plugin_unid() {
        static $unid;

        if ( !$unid ) {
            $unid = ( @include_once self::plugin_unid_path() );
        }

        return $unid;
    }
    public static function plugin_unid_path() {
        return plugin_dir_path( __FILE__ ) . 'fcp-forms-unid.php'; // wp_get_upload_dir()['basedir'] . '/'
    }

    public static function form_exists($dir = '') { // ++ can it be private?
        if ( !$dir ) { return false; }

        $path = plugin_dir_path( __FILE__ ) . 'forms/' . $dir . '/structure.json';
        if ( !is_file( $path ) ) { return false; }
        
        return true;
    }

    public static function structure($dir = '') {
        if ( !$dir ) { return false; }

        $path = plugin_dir_path( __FILE__ ) . 'forms/' . $dir . '/structure.json';
        if ( !is_file( $path ) ) { return false; }

        $cont = file_get_contents( $path );
        if ( $cont === false ) { return false; }

        $json = json_decode( $cont, false );
        if ( $json === null ) { return false; }

        $json->options->form_name = $dir;

        // ++ add prefixes here // self::$prefix.'_'

        return $json;
    }


    // the following are used in different types of forms or fields

    public static function email_to_user($email) { // split an email to first and last name
        $person = ['me', 'person', 'name'];
        $zone = substr( $email, strrpos( $email, '.' ) + 1 );
        
        if ( in_array( $zone, $person ) ) {
            $crop = substr( $email, 0, strrpos( $email, '.' ) );
            list( $n['first_name'], $n['last_name'] ) = explode( '@', $crop );
        } else {
            $crop = substr( $email, 0, strrpos( $email, '@' ) );
            list( $n['first_name'], $n['last_name'] ) = explode( '.', $crop );   
        }
        
        $n = array_map( 'ucfirst', $n );
        $n['display_name'] = $n['first_name'] . ' ' . $n['last_name'];
        $n['user_login'] = sanitize_user( $n['first_name'] . $n['last_name'], true );
        
        $n['user_email'] = $email;
        
        // check if user login exists && create a new one
        require_once ABSPATH . WPINC . '/user.php';
        $init_login = $n['user_login'];
        $counter = 2;
        while( username_exists( $n['user_login'] ) ) {
            $n['user_login'] = $init_login . $counter;
            $counter++;
        }
        
        return $n;

    }
    
    public static function check_role($role, $user = []) {
        if ( is_numeric( $user ) ) {
            $user = get_user_by( 'ID', $user );
        }
    
        if( empty( $user ) ) {
            $user = wp_get_current_user(); //instance of WP_Error filter??
        }

        if( empty( $user ) || !in_array( $role, (array) $user->roles ) ) {
            return false;
        }

        return [$role, $user];
    }
    
    public static function role_allow($a = []) {
        return !empty( array_intersect( self::roles_get(), $a ? $a : [] ) );
    }
    private static function roles_get() {
        static $roles = [];
        if ( empty( $roles ) ) {
            $roles = get_userdata( get_current_user_id() )->roles;
        }
        return $roles;
    }
    
    // json manipulation
    
    public static function json_attr_by_name(&$fields, $name, $key, $value = '', $unset = false) {
        // ++ add an option to add $value to existing one
        if ( !is_array( $fields ) || !$name || !$key ) { return; }

        foreach ( $fields as $k => &$f ) {

            if ( $f->gtype ) {
                if ( $result = self::json_attr_by_name( $f->fields, $name, $key, $value, $unset ) ) {
                    return $result;
                }
                continue;
            }

            if ( !$f->type || $f->name !== $name ) { continue; }

            // get / return
            if ( !$value && !$unset ) {
                return $f->{ $key };
            }
            
            // add || edit
            if ( !$unset ) {
                $f->{ $key } = $value;
                return;
            }
            // ++if is_array( $key ) - merge

            // delete
            if ( $unset && ( $value === $f->{ $key } || !$value ) ) {
                unset( $f->{ $key } );
            }

        }

    }

    public static function json_field_by_sibling(&$fields, $name, $structure = [], $command = '') {

        if ( !is_array( $fields ) || !$name ) { return; }

        foreach ( $fields as $k => &$f ) {
            
            if ( $f->gtype ) {
                if ( $result = self::json_field_by_sibling( $f->fields, $name, $structure, $command ) ) {
                    return $result;
                }
                continue;
            }

            if ( !$f->type || $f->name !== $name ) { continue; }

            if ( !$structure && $command !== 'unset' ) {
                return $f;
            }
            
            switch ( $command ) {
                case 'unset' :
                    unset( $fields[ $k ] );
                    $fields = array_values( $fields );
                    return;
                case 'merge' :
                    $f = (object) array_merge( (array) $f, $structure );
                    return;
                case 'override' :
                    $f = (object) $structure;
                    return;
                case 'before' :
                    array_splice( $fields, $k, 0, [ (object) $structure ] );
                    return;
                default : // after
                    array_splice( $fields, $k + 1, 0, [ (object) $structure ] );
                    return;
            }

        }
    }
    
    public static function json_change_field(&$fields, $field, $key, $value) { //++ DELETE ME

        foreach ( $fields as &$f ) {

            if ( $f->type ) {
                if ( $f->name === $field ) {
                    $f->{ $key } = $value;
                }
                // ++ unset attr
                // ++ unset field
                continue;
            }

            if ( $f->gtype ) {
                self::json_change_field( $f->fields, $field, $key, $value );
            }

        }
        
        return $fields;

    }
    
    // timezones
    public static function tz_set($tz = '') {
        $tz = $tz ? $tz : 'UTC';
        self::$tz = date_default_timezone_get(); // store for reset
        if ( $tz !== self::$tz ) {
            date_default_timezone_set( $tz );
        }
    }
    public static function tz_reset() {
        $tz = date_default_timezone_get();
        if ( $tz !== self::$tz ) {
            date_default_timezone_set( self::$tz );
        }
    }

}

new FCP_Forms();

/*
    globalize delegate register styles
    filter multiple fields empty values, as schedule fills in too many rows
    make the password validate simple test
    front-end validation
        autofill with front-end validation
    aria
    on clear trash - remove the clinic imgs dir
    img preview in wp-admin
    print tmp dir only if a file type exists
    instead of number of isset-s - use a blueprint of allowed array and default values and autofill the default values
//*/
