<?php

class FCP_Add_Meta_Boxes {

    private $s, $p; // structure, preferences

    public static function version() {
        return '2.0.0';
    }

    public function __construct($s, $p) {
    
        if ( !$s || !class_exists( 'FCP_Forms__Draw' ) ) { return; }

        $this->s = $s;
        $this->p = $p;
        $this->p->warn_name = 'fcp-form--'.$s->options->form_name.'--warnings';

        add_action( 'add_meta_boxes', [ $this, 'addMetaBoxes' ] ); // ++add post type filter as it treiggers on common pages too
        add_action( 'save_post', [ $this, 'saveMetaBoxes' ] );
    }

    public function addMetaBoxes() {
        global $post;
        
        $p = $this->p;

        // get meta values
        $values0 = get_post_meta( $post->ID );
        // meta names to structure names
        $fields = FCP_Forms::flatten( $this->s->fields );
        $values = [];
        foreach ( $fields as $f ) {

            if ( !isset( $f->name ) || !isset( $values0[ $f->name ] ) ) { continue; }
            
            $values[ $f->name ] = $values0[ $f->name ][0];

            if (
                isset( $f->multiple ) ||
                $f->type === 'checkbox' && count( (array) $f->options ) > 1 ||
                $f->type === 'file'
            ) {
                $values[ $f->name ] = unserialize( $values[ $f->name ] );
            }
            
            if ( $f->type === 'file' ) {
                $values[ $f->name . '--uploaded' ] = $values[ $f->name ];
                unset( $values[ $f->name ] );
            }
        }

        // add warnings
        if ( isset( $_COOKIE[ $p->warn_name ] ) ) {
            foreach ( $_COOKIE[ $p->warn_name ] as $k => $v ) {
                $values[ $p->warn_name ][$k] = json_decode( stripslashes( $v ) );
                $values[ $p->warn_name ][$k][] = __( 'The Initial value is restored', 'fcpfo' );
                setcookie( $p->warn_name.'['.$k.']', '', time()-3600, '/' );
            }
            unset( $_COOKIE[ $p->warn_name ] );
            
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice error my-acf-notice is-dismissible" >
                    <p>
        <?php _e( 'Some fields were not filled correctly. Please, correct the values and submit again.', 'fcpfo' ) ?>
                    </p>
                    <style>#message{display:none}</style>
                </div>
                <?php
                // ++ disable sending the email
            } );
        }
        
        // ++add the post_type filter here or even higher!!!
        @include_once( __DIR__ . '/../forms/' . $this->s->options->form_name . '/override-admin.php' );

        // print meta fields
        $draw = new FCP_Forms__Draw( $this->s, $values );

		add_meta_box(
            $this->s->options->form_name,
            __( $p->title, isset( $p->text_domain ) ? $p->text_domain : '' ),
            [ $draw, 'print_meta_boxes' ],
            $p->post_types,
            $p->context,
            $p->priority
        );

    }

	public function saveMetaBoxes($postID) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if (
            !isset( $_POST[ 'fcp-form--' . $this->s->options->form_name ] ) ||
            !wp_verify_nonce( $_POST[ 'fcp-form--' . $this->s->options->form_name ], FCP_Forms::plugin_unid() )
        ) { return; }
		if ( !current_user_can( 'edit_post', $postID ) ) { return; }

        $post = get_post( $postID );
        if ( $post->post_type == 'revision' ) { return; } // ++ maybe move only to the uploading process, as I don't know how revisions work for now

        // don't save wrongly formatted fields
        if ( is_admin() ) {
            if ( isset( $_FILES ) && !class_exists( 'FCP_Forms__Files' ) ) {
                include_once __DIR__ . '/files.class.php';
            }

            if ( !class_exists( 'FCP_Forms__Validate' ) ) {
                include_once __DIR__ . '/validate.class.php';
            }
            $warns = new FCP_Forms__Validate( $this->s, $_POST, $_FILES ); // ++ if !current_user_can('administrator')
            
            //print_r( [$warns->result, $warns->files_failed] ); exit;
            
            if ( isset( $_FILES ) ) {
                $uploads = new FCP_Forms__Files( $this->s, $_FILES, $warns->files_failed ); // ++roles filter is below :(
            }
            
            // modify data before save && process files
            @include_once( __DIR__ . '/../forms/' . $this->s->options->form_name . '/process-admin.php' ); // ++move to fcp-forms.php hook??

        }

        $fields = FCP_Forms::flatten( $this->s->fields );
        foreach ( $fields as $f ) {
            if ( !isset( $f->meta_box ) || !isset( $f->name ) || !$f->name || !isset( $f->type ) || $f->type === 'none' ) { continue; }
            
            // the options for select are required even if itself is not
            if ( $f->type === 'select' && !empty( $_POST[ $f->name ] ) && !isset( $f->options->{ $_POST[ $f->name ] } ) ) {
                continue;
            }
            
            if ( isset( $f->roles_edit ) && !FCP_Forms::role_allow( $f->roles_edit ) ) { continue; }
            if ( 
                isset( $f->roles_view ) && FCP_Forms::role_allow( $f->roles_view ) &&
                (
                    !isset( $f->roles_edit ) || // ++this is something strange - check where is used and fix
                    isset( $f->roles_edit ) && !FCP_Forms::role_allow( $f->roles_edit )
                )
            ) { continue; }

            if ( isset( $warns->result[ $f->name ] ) ) { // ++ && is_admin() ?
                setcookie(
                    $this->p->warn_name.'['.$f->name.']',
                    json_encode( $warns->result[ $f->name ] ),
                0, '/' );
                continue;
            }

            if ( empty( $_POST[ $f->name ] ) ) {
                delete_post_meta( $postID, $f->name );
                continue;
            }
            update_post_meta( $postID, $f->name, $_POST[ $f->name ] );
        }
        
        //clean_post_cache( $postID ); // ++not sure it is needed

	}

	
}
