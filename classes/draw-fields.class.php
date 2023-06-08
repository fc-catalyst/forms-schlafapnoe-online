<?php

class FCP_Forms__Draw {

    private $s; // structure
    public $result; // contains the final form html

    public function __construct($s, $v = [], $f = []) {

        $v = $v ? $v : []; // probably, remove those in later versions of php
        $f = $f ? $f : [];

        if ( isset( $v['fcp-form--'.$s->options->form_name.'--warning'] ) ) {
            $s->options->warning = $v['fcp-form--'.$s->options->form_name.'--warning'];
        }
        $s->options->required_mark = isset( $s->options->required_mark ) ? $s->options->required_mark : '*';

        $this->s = $s;
        $this->s->fields = $this->add_values( $s->fields, array_merge( $v, $f ) );
        if ( !is_admin() ) {
            $this->result = $this->printFields(); // ++this return breaks wp_editor, which doesn't include parts. echo works. basic solution would be to include the script separately. It can be needed in terms of tinymce front-end usage
        }

    }

    private function add_values(&$f, $v) {
        foreach ( $f as &$add ) {

            if ( isset( $add->gtype) ) {
                $this->add_values( $add->fields, $v );
                continue;
            }
        
            if ( isset( $add->type ) ) {
                if ( isset( $this->s->options->form_name ) && isset( $add->name ) && isset( $v['fcp-form--'.$this->s->options->form_name.'--warnings'][ $add->name ] ) ) {
                    $add->warning = $v['fcp-form--'.$this->s->options->form_name.'--warnings'][ $add->name ];
                }
                $add->savedValue = isset( $add->name ) && isset( $v[ $add->name ] ) ? $v[ $add->name ] : '';

                if ( $add->type === 'file' ) {
                    $add->uploaded_fields = $add->name.'--uploaded';
                    $add->uploaded_files = empty( $v[ $add->uploaded_fields ] ) ? [] : $v[ $add->uploaded_fields ];
                }
            }

        }
        return $f;
    }
    
    private function field_notice($a) {
        echo $a->text;
    }
    private function field_notice_view($a) {
        echo isset( $a->view ) ? $a->view->before . $a->text . $a->view->after : $a->text;
    }

    private function field_text($a) {

        $value = isset( $a->savedValue ) && $a->savedValue ? $a->savedValue : ( isset( $a->value ) ? $a->value : '' ); //++ can unite the following to a function
        $value = is_array( $value ) ? $value : [ $value ];

        foreach ( $value as $k => $v ) {

            if ( empty( $v ) && !isset( $a->keep_empty ) && count( array_values( $value ) ) > 1 ) {
                continue;
            }

            $k = is_numeric( $k ) ? '' : $k;

        ?>
        <input
            type="text"
            name="<?php $this->e_field_name( $a->name ) ?><?php echo isset( $a->multiple ) ? '['.$k.']' : '' ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            <?php echo isset( $a->size ) ? 'size="'.$a->size.'" style="width:auto;"' : '' ?>
            placeholder="<?php echo isset( $a->placeholder ) ? $a->placeholder  : '' ?>"
            value="<?php echo esc_attr( $v ) ?>"
            class="<?php echo isset( $a->warning ) ? 'fcp-f-invalid' : '' ?>"
            <?php echo isset( $a->autofill ) ? 'data-fcp-autofill="'.$a->autofill.'"' : '' ?>
            <?php echo isset( $a->autocomplete ) ? 'autocomplete="'.$a->autocomplete.'"' : '' ?>
        />
        <?php
        
        }
    }
    private function field_text_view($a) {

        $value = $a->savedValue ? $a->savedValue : $a->value;
        $value = is_array( $value ) ? $value : [ $value ];

        foreach ( $value as $v ) {

            if ( empty( $v ) && !$a->keep_empty && count( $value ) > 1 ) {
                continue;
            }

            echo $a->view->before . $v . $a->view->after;// ? $v : __( 'No value set', 'fcpfo' );
        
        }
    }
    
    private function field_password($a) {
        ?>
        <input
            type="password"
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            <?php echo isset( $a->size ) ? 'size="'.$a->size.'" style="width:auto;"' : '' ?>
            placeholder="<?php echo $a->placeholder ?>"
            class="<?php echo isset( $a->warning ) ? 'fcp-f-invalid' : '' ?>"
        />
        <?php
    }
    
    private function field_button($a) {
        ?>
        <button
            type="button"
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            class="<?php echo !empty( $a->warning ) ? 'fcp-f-invalid' : '' ?><?php echo $this->smaller_font( $a->title, $this->s->options->reduce_font_after - 2 ) ?>"
        ><?php echo $a->value ?></button>
        <?php
    }
    
    private function field_hidden($a) {
        ?>
        <input
            type="hidden"
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            value="<?php echo esc_attr( isset( $a->savedValue ) && $a->savedValue ? $a->savedValue : ( isset( $a->value ) ? $a->value : '' ) ) ?>"
            <?php echo isset( $a->autofill ) ? 'data-fcp-autofill="'.$a->autofill.'"' : '' ?>
        />
        <?php
    }
    
    private function field_textarea($a) {
    
        if ( $a->tinymce === true || is_admin() && $a->tinymce === 'admin' || !is_admin() && $a->tinymce === 'front' ) {
            $buttons = ['undo', 'redo', '|', 'formatselect', 'bold', 'italic', '|', 'link', 'unlink', '|', 'bullist', 'numlist'];
        
            wp_editor(
                isset( $a->savedValue ) && $a->savedValue ? $a->savedValue : ( isset( $a->value ) ? $a->value : '' ),
                $this->__field_id( $a->name ),
                [
                    'media_buttons' => 0,
                    'textarea_name' => $this->__field_name( $a->name ),
                    'textarea_rows' => $a->rows ? $a->rows : '20',
                    'tinymce' => [
                        'toolbar1' => implode( ',', $buttons )
                    ],
                    'quicktags'     => [
                        'buttons' => 'none'
                    ]
                ]
                // h1 & h2 are disabled in fcp-forms.php
            );
            return;
        }

        ?>
        <textarea
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            rows="<?php echo $a->rows ? $a->rows : '10' ?>" cols="<?php echo $a->cols ? $a->cols : '50' ?>"
            placeholder="<?php echo $a->placeholder ?>"
            class="<?php echo $a->warning ? 'fcp-f-invalid' : '' ?>"
            <?php echo $a->autofill ? 'data-fcp-autofill="'.$a->autofill.'"' : '' ?>
        ><?php echo esc_textarea( isset( $a->savedValue ) && $a->savedValue ? $a->savedValue : $a->value ) ?></textarea>
        <?php
    }

    private function field_checkbox($a) {
        ?>
        
        <fieldset
            id="<?php $this->e_field_id( $a->name ) ?>"
            class="
                <?php echo isset( $a->cols ) ? 'fcp-form-cols-' . $a->cols : '' ?>
                <?php echo !empty( $a->warning ) ? 'fcp-f-invalid' : '' ?>
            "
        >
        
        <?php
        $single = count( (array) $a->options ) == 1 ? true : false;
        foreach ( $a->options as $k => $b ) :
            $checked = $single && $k == $a->savedValue || is_array( $a->savedValue ) && in_array( $k, $a->savedValue );
        ?>
            <label>
                <input type="checkbox"
                    name="<?php $this->e_field_name( $a->name ) ?><?php echo $single ? '' : '[]' ?>"
                    value="<?php echo esc_attr( $k ) ?>"
                    <?php echo $checked ? 'checked' : '' ?>
                >
                <span><?php echo $b ?></span>
            </label>

        <?php
        endforeach;

        ?>
        
        </fieldset>
        
        <?php
        
    }

    private function field_radio($a) {
        ?>
        
        <fieldset 
            id="<?php $this->e_field_id( $a->name ) ?>"
            class="
                <?php echo isset( $a->cols ) ? 'fcp-form-cols-' . $a->cols : '' ?>
                <?php echo !empty( $a->warning ) ? 'fcp-f-invalid' : '' ?>
            "
        >
        
        <?php
        foreach ( $a->options as $k => $b ) :
            ?>
            <label>
                <input type="radio"
                    name="<?php $this->e_field_name( $a->name ) ?>"
                    value="<?php echo esc_attr( $k ) ?>"
                    <?php echo $k == $a->savedValue ? 'checked' : '' ?>
                >
                <span><?php echo $b ?></span>
            </label>
            <?php
        endforeach;

        ?>
        
        </fieldset>
        
        <?php
        
    }
    
    private function field_select($a) {

        $value = isset( $a->savedValue ) && $a->savedValue ? $a->savedValue : ( isset( $a->value ) ? $a->value : '' );
        $value = is_array( $value ) ? $value : [ $value ];

        ?>
        <select
            name="<?php $this->e_field_name( $a->name ) ?><?php echo isset( $a->multiple ) ? '[]' : '' ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            class="<?php echo !empty( $a->warning ) ? 'fcp-f-invalid' : '' ?>"
            <?php echo isset( $a->multiple ) ? 'multiple' : '' ?>
        >
            <?php
                if ( isset( $a->placeholder ) ) {
                    ?>
                    <option value=""><?php echo $a->placeholder ?></option>
                    <?php
                }

                // translations
                if ( isset( $this->s->options->translation ) ) {
                    $domain = $this->s->options->translation;
                }

                foreach ( $a->options as $k => $b ) :
                    ?>
                    <option
                        value="<?php echo esc_attr( $k ) ?>"
                        <?php echo in_array( $k, $value ) ? 'selected' : '' ?>
                    >
                            <?php echo isset( $domain ) ? __( $b, $domain ) : $b ?>
                    </option>
                    <?php
                endforeach;
            ?>
        </select>
        <?php
    }

    private function field_select_view($a) {

        $value = $a->savedValue ? $a->savedValue : $a->value;
        $value = is_array( $value ) ? $value : [ $value ];

        $result = [];
        foreach ( $a->options as $k => $b ) {
            if ( in_array( $k, $value ) ) {
                $result[] = $b;
            }
        }
        echo $a->view->before . implode( $a->view->sep, $result ) . $a->view->after;
    }


    private function field_datalist($a) {
        ?>
        <input
            type="text"
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            list="<?php $this->e_field_id( $a->name ) ?>-list"
            <?php echo $a->size ? 'size="'.$a->size.'" style="width:auto;"' : '' ?>
            placeholder="<?php echo $a->placeholder ?>"
            value="<?php echo esc_attr( $a->savedValue ? $a->savedValue : $a->value ) ?>"
            class="<?php echo $a->warning ? 'fcp-f-invalid' : '' ?>"
            <?php echo isset( $a->autocomplete ) ? 'autocomplete="'.$a->autocomplete.'"' : '' ?>
        />
        <datalist id="<?php $this->e_field_id( $a->name ) ?>-list">
        <?php

            foreach ( $a->options as $b ) :
                ?>
                <option value="<?php echo esc_attr( $b ) ?>">
                <?php
            endforeach;
        ?>
        </datalist>
        <?php
    }

    private function field_file($a) {
        if ( !empty( $a->uploaded_files ) ) {
            $count = count( $a->uploaded_files );
            //$label = $count == 1 ? $a->uploaded_files[0] : $count . ' Files Uploaded';
            $label = $count === 1 ?
                __( '1 file uploaded:', 'fcpfo' ) :
                sprintf( __( '%s files uploaded:', 'fcpfo' ), $count );
        }
    
        ?>
        <input
            type="file"
            name="<?php $this->e_field_name( $a->name ) ?><?php echo isset( $a->multiple ) ? '[]' : '' ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            class="
                <?php echo empty( $a->savedValue ) ? 'fcp-form-empty' : '' ?>
                <?php echo !empty( $a->warning ) ? 'fcp-f-invalid' : '' ?>
            "
            <?php echo isset( $a->size ) ? 'size="'.$a->size.'" style="width:auto;"' : '' ?>
            <?php echo isset( $a->multiple ) ? 'multiple' : '' ?>
            data-select-file="<?php _e( 'Select File', 'fcpfo' ) ?>"
            data-select-files="<?php _e( 'Select Files', 'fcpfo' ) ?>"
            data-files-selected="<?php _e( 'files selected', 'fcpfo' ) ?>"
        />
        <label for="<?php $this->e_field_id( $a->name ) ?>">
            <?php echo !empty( $label ) ? $label : __( isset( $a->multiple ) ? 'Select Files' : 'Select File', 'fcpfo' ) ?>
        </label>
        <?php

        if ( !empty( $a->uploaded_files ) ) {
        
            ?><fieldset><?php
            foreach ( $a->uploaded_files as $v ) :
            ?>
                <label>
                    <input type="checkbox" checked
                        name="<?php $this->e_field_name( $a->uploaded_fields ) ?>[]"
                        value="<?php echo esc_attr( $v ) ?>"
                    />
                    <span><?php echo $v ?></span>
                </label>

            <?php
            // ++ can print the images thumbnails somewhere here or inside the label
            endforeach;
            ?></fieldset><?php

        }
    }

    private function field_submit($a) {
        ?>
        <input
            type="submit"
            name="<?php $this->e_field_name( $a->name ) ?>"
            id="<?php $this->e_field_id( $a->name ) ?>"
            <?php echo isset( $a->size ) ? 'size="'.$a->size.'" style="width:auto;"' : '' ?>
            value="<?php echo esc_attr( $a->value ) ?>"
        />
        <?php
    }
    
    private function field_rscaptcha($a) {
        self::rscaptcha_print($a);
    }
    public static function rscaptcha_print($a) {
        if ( !class_exists( 'ReallySimpleCaptcha' ) ) { return; }

        if ( isset( $a->async ) ) {
        
        ?>
        <div class="replace-with-recaptcha <?php echo self::field_name_clean( $a->name ) ?>"></div>
        <script>
            fcLoadScriptVariable( '', 'jQuery', function() {
                let t = '';
                <?php if ( $a->async === 'hard' ) { // cach a random ?>
                const d = new Date();
                t = + d + d.getMilliseconds();
                <?php } // / cach a random ?>
                jQuery.get( '/wp-json/fcp-forms/v1/rscaptcha/' + t, function( data ) { 
                    jQuery( '.replace-with-recaptcha.<?php echo self::field_name_clean( $a->name ) ?>' ).replaceWith( data.content );
                });
            });
        </script>
        <?php

            return;
        }
        
        $b = new ReallySimpleCaptcha();
        $b->cleanup( $a->prefs->cleanup_minutes ? $a->prefs->cleanup_minutes : 60 ); // 60 is the plugin's default
        $prefs = [ 'chars', 'char_length', 'fonts', 'tmp_dir', 'img_size', 'bg', 'fg', 'base', 'font_size', 'font_char_width', 'img_type' ];
        
        foreach ( $prefs as $v ) {

            // select random variant
            $w = $v . '_vars';
            if ( isset( $a->prefs->{ $w } ) ) {
                $a->prefs->{ $v } = $a->prefs->{ $w }[ array_rand( $a->prefs->{ $w } ) ];
            }
            if ( !isset( $a->prefs->{ $v } ) ) { continue; }

            if ( $v === 'fonts' ) {
                foreach ( $a->prefs->{ $v } as $m => &$u ) {
                    $path = __DIR__ . '/../assets/captcha-fonts/' . $u;
                    if ( !is_file( $path ) ) {
                        unset( $a->prefs->{ $v }[ $m ] );
                        continue;
                    }
                    $u = $path;
                }
                if ( empty( $a->prefs->{ $v } ) ) {
                    unset( $a->prefs->{ $v } );
                }
            }

            $b->{ $v } = $a->prefs->{ $v } ? $a->prefs->{ $v } : $b->{ $v };
        }

        $word = $b->generate_random_word();
        $prefix = mt_rand();
        $src = $b->generate_image( $prefix, $word );
        ?>
        <input
            type="input"
            name="<?php echo self::field_name_clean( $a->name ) ?>"
            id="<?php echo self::field_name_clean( $a->name ) ?>"
            style="width:<?php echo $b->img_size[0] ?>px;height:<?php echo $b->img_size[1] ?>px"
            placeholder="<?php echo $a->placeholder ?>"
            class="<?php echo isset( $a->warning ) ? 'fcp-f-invalid' : '' ?>"
        />
        <span></span>
        <img
            src="<?php echo plugins_url( 'really-simple-captcha/tmp/' . $src ) ?>"
            style="width:<?php echo $b->img_size[0] ?>px;height:<?php echo $b->img_size[1] ?>px"
        />
        <input
            type="hidden"
            name="<?php echo self::field_name_clean( $a->name ) ?>_prefix"
            id="<?php echo self::field_name_clean( $a->name ) ?>_prefix"
            value="<?php echo esc_attr( $prefix ) ?>"
        />
        <?php    
    }
    
    private function field__wrap($a, $method) {
        
        $o = $this->s->options;
        
        if ( $a->type == 'hidden' ) {
            $this->{ $method }( $a );
            return;
        }

        // translations ++move separately?
        if ( isset( $this->s->options->translation ) && $domain = $this->s->options->translation ) {
            foreach ( ['title', 'placeholder', 'subtitle', 'description'] as $v ) {
                if ( !isset( $a->{ $v } ) ) { continue; }
                $a->{ $v } = __( $a->{ $v }, $domain );
            }
        }

        $a->__required = ( isset( $a->validate ) && ( isset( $a->validate->notEmpty ) || isset( $a->validate->minLetters ) && $a->validate->minLetters > 0 ) ) ? $o->required_mark : '';
        if ( isset( $a->placeholder ) ) {
            $a->placeholder = isset( $a->__required ) && !isset( $a->title ) ? $a->placeholder . $a->__required : $a->placeholder;
        }
        ?>
        <div class="fcp-form-field-w">
        <?php echo isset( $a->before ) ? $a->before : '' ?>
        <?php
        
        if ( isset( $a->title ) ) {
            ?>
            <span class="fcp-form-field-h<?php echo $this->smaller_font( $a->title, $o->reduce_font_after ) ?>">
                <?php echo $a->title ?><?php echo $a->__required ?>
                <?php echo isset( $a->subtitle ) ? ' <small>'.$a->subtitle.'</small>' : '' ?>
            </span>
            <?php
        }

        $this->{ $method }( $a );
        
        if ( isset( $a->description ) ) {
            ?>
            <div class="fcp-form-field-d"><?php echo $a->description ?></div>
            <?php
        }

        if ( isset( $a->warning ) && $a->warning ) {
            ?>
            <ul class="fcp-form-field-warn"><li><?php echo implode( "</li>\n<li>", $a->warning ) ?></ul>
            <?php
        }

        ?>
        <?php echo isset( $a->after ) ? $a->after : '' ?>
        </div>
        <?php
    }
    
    private function printFields() {
        ob_start();
        $o = $this->s->options;
        ?>

        
        <?php echo isset( $o->before ) ? $o->before : '' ?>
        <form
            class="fcp-form
                <?php echo isset( $o->inherit_styles ) && $o->inherit_styles === false ? '' : 'fcp-form--main' ?>
                <?php echo 'fcp-form-' . $o->form_name ?>
                <?php echo isset( $o->width ) ? 'fcp-form--' . $o->width : '' ?>
            "
            method="<?php echo $o->method ? $o->method : 'post' ?>"
            <?php echo isset( $o->enctype ) ? 'enctype="'.$o->enctype.'"' : '' ?>
            <?php echo isset( $o->autocomplete ) ? 'autocomplete="'.$o->autocomplete.'"' : '' ?>
        >
        
        <?php
        if ( !empty( $o->warning ) ) {
            ?>
            <div class="fcp-form-warning"><?php echo $o->warning ?></div>
            <?php
        }
        foreach ( $this->s->fields as $f ) {
            if ( isset( $f->type ) ) { // common field print
                $this->printField( $f );
                continue;
            }
            if ( isset( $f->gtype ) ) { // group of fields print
                $this->printGroup( $f );
            }
        }
        ?>

        <?php wp_nonce_field( FCP_Forms::plugin_unid(), 'fcp-form--' . $o->form_name ) ?>
        <input type="hidden" name="fcp-form-name" value="<?php echo $o->form_name ?>" />
        <input type="hidden" name="fcp-form--tmpdir"
            value="<?php echo !empty( $_POST['fcp-form--tmpdir'] ) ? $_POST['fcp-form--tmpdir'] : FCP_Forms::unique() ?>"
        />
        </form>
        <?php echo isset( $o->after ) ? $o->after : '' ?>


        <?php
        $content = ob_get_contents();
        ob_end_clean();

        return self::align_html_codes( $content );
    }
    
    private function printField($f) {
        if ( is_admin() && !isset( $f->meta_box ) ) { return; }
        if ( !is_admin() && isset( $f->meta_box ) && $f->meta_box === 'only' ) { return; }
        if ( isset( $f->roles_view ) && FCP_Forms::role_allow( $f->roles_view ) ) {
            $method = 'field_' . $f->type . '_view';
        }
        if ( !isset( $f->roles_view ) && !isset( $f->roles_edit ) || isset( $f->roles_edit ) && FCP_Forms::role_allow( $f->roles_edit ) ) {
            $method = 'field_' . $f->type;
        }
        if ( !isset( $method ) || !method_exists( $this, $method ) ) { return; }
        if ( isset( $f->options ) ) { $f->options = $this->order( $f ); }
        $this->field__wrap( $f, $method );
    }

    private function printGroup($f) {

        /*
        if ( $f->gtype == 'hidden' ) { // for the hiddens display
            echo '<input type="checkbox" aria-hidden="true" id="' . $f->id . '-tumbler" />';
        }
        //*/

        ?>
        <div 
            class="
                fcp-form-group
                fcp-form-group-<?php echo $f->gtype ?>
                <?php echo isset( $f->cols ) ? ' fcp-form-cols-'.$f->cols : '' ?>
            "
            <?php echo isset( $f->id ) ? 'id="'.$f->id.'"' : '' ?>
        >
        <?php
            if ( isset( $f->title ) || isset( $f->description ) ) {
                ?>
                <div class="fcp-form-group-h">
                <?php
                if ( $f->title ) {
                    $h2 = isset( $f->title_tag ) ? $f->title_tag : 'h2';
                    ?>
                    <<?php echo $h2 ?>>
                        <?php echo isset( $f->title ) ? $f->title : '' ?>
                        <?php echo isset( $f->subtitle ) ? ' <small>'.$f->subtitle.'</small>' : '' ?>
                    </<?php echo $h2 ?>>
                    <?php
                }

                if ( isset( $f->description ) ) {
                    ?>
                    <div class="fcp-form-group-d"><?php echo $f->description ?></div>
                    <?php
                }
                ?>
                </div>
                <?php
            }
        ?>
        <?php
        if ( is_array( $f->fields ) ) {
            foreach ( $f->fields as $gf ) {
                if ( isset( $gf->type ) ) { // common field print
                    $this->printField( $gf );
                    continue;
                }
                if ( isset( $gf->gtype ) ) { // group of fields print
                    $this->printGroup( $gf );
                }
            }
        }

        /*
        if ( $f->gtype == 'hidden' ) { // hiddens' close buttons
            echo '<label for="' . $f->id . '-tumbler" class="fcp-form-group-done">Done</label>';
            echo '<label for="' . $f->id . '-tumbler" class="fcp-form-group-close">Close</label>';
        }
        //*/

        ?>
        </div>
        <?php
    }

    private function e_field_id($field_name) {
        echo $this->__field_id( $field_name );
    }
    private function __field_id($field_name) {
        return self::field_name_clean(
            $field_name .
            ( $this->s->options->form_name ? '_' . $this->s->options->form_name : '' )
        );
    }
    
    private function e_field_name($field_name) {
        echo $this->__field_name( $field_name );
    }
    private function __field_name($field_name) {
        return self::field_name_clean( $field_name );
    }
    
    private static function field_name_clean($field_name) {
        return $field_name;
    }
    
    private function smaller_font($title, $max_length) {
        if ( !$title || !is_numeric( $max_length ) || strlen( $title ) <= $max_length ) { return; }
        return ' fcp-form-small';
    }

    private function order( $f ) {
        if ( !isset( $f->order ) ) { return $f->options; }
        $f->options = (array) $f->options;
        if ( $f->order[0] === 'key' ) {
            if ( $f->order[1] === 'ASC' ) {
                ksort( $f->options );
            }
            if ( $f->order[1] === 'DESC' ) {
                krsort( $f->options );
            }
        }
        if ( $f->order[0] === 'value' ) {
            if ( $f->order[1] === 'ASC' ) {
                asort( $f->options );
            }
            if ( $f->order[1] === 'DESC' ) {
                arsort( $f->options );
            }
        }
        return (object) $f->options;
    }

    public function print_meta_boxes() {
        ob_start();
        $o = $this->s->options;
        
        ?><div class="fcp-form fcp-form--half"><?php
        
        foreach ( $this->s->fields as $f ) {
            if ( isset( $f->type ) ) { // common field print
                $this->printField( $f );
                continue;
            }
            if ( isset( $f->gtype ) ) { // group of fields print
                $this->printGroup( $f );
            }
        }
        
        wp_nonce_field( FCP_Forms::plugin_unid(), 'fcp-form--' . $o->form_name );

        ?></div><?php
        
        $content = ob_get_contents();
        ob_end_clean();

        echo self::align_html_codes( $content );

    }
    
    public static function align_html_codes($c) {

        $init = $c; // store if a heavy preg throws an error ( can catch it with preg_last_error() )

        // don't touch the textareas' & pres' content
        $c = preg_replace_callback( '/(<(textarea|pre)[^>]*>)((?:.|\n)*?)(<\/\\2>)/', function( $m ) {
            return $m[1] . base64_encode( $m[3] ) . $m[4];
        }, $c );
        if ( $c === null ) { return $init; }
        
        $c = trim( $c );
        $c = preg_replace( '/\s+/', ' ', $c );
        $c = preg_replace( '/ </', "\n<", $c ); //++can list the block tags for this case //--inlines misses spaces :(
        
        $c = preg_replace_callback( '/(<(textarea|pre)[^>]*>)(.*?)(<\/\\2>)/', function( $m ) {
            return $m[1] . base64_decode( $m[3] ) . $m[4];
        }, $c );
        if ( $c === null ) { return $init; }

        return $c;
    }
    
}
