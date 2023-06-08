<?php

class FCP_Forms__Validate {

    private $s, $v, $p; // structure; $_POST + $_FILES
    public $result, $files_failed; // text warnings; [field name][] = failed file name

    public function __construct($s, $v, $f = []) {

        $this->s = $s;
        $this->s->fields = FCP_Forms::flatten( $s->fields );
        $this->v = $v + $f;

        $this->checkValues();
    }


    private function test_name($rule, $a) {
        return self::name( $rule, $a );
    }
    public static function name($rule, $a) {
        if ( !$a || $a && $rule == true && preg_match( '/^[a-zA-Z0-9-_]+$/', $a ) ) {
            return false;
        }
        return __( 'Must contain only letters, numbers or the following symbols: "-", "_"', 'fcpfo' );
    }

    private function test_notEmpty($rule, $a) { //++ add notEmptyHTML??
        if ( !$rule ) {
            return false;
        }
        
        $a = is_string( $a ) ? trim( $a ) : $a;
        if ( !empty( $a ) ) {
            return false;
        }
        return __( 'The field is required', 'fcpfo' );
    }
    
    private function test_regExp($rule, $a) {
        if ( !$a || $a && $rule[0] && preg_match( '/'.$rule[0].'/', $a ) ) {
            return false;
        }
        return sprintf( __( 'Doesn\'t fit the pattern %s', 'fcpfo' ), isset( $rule[1] ) ? $rule[1] : '' );
    }

    private function test_email($rule, $a) {
        if ( !$a || $a && $rule == true && is_email( $a ) ) {
            return false;
        }
        return __( 'The email format is incorrect', 'fcpfo' );
    }

    private function test_url($rule, $a) {
        if ( !$a || $a && $rule == true && filter_var( $a, FILTER_VALIDATE_URL ) ) {
            return false;
        }
        return __( 'Please, start the link with https:// or http://', 'fcpfo' );
    }

    private function test_equals($rule, $a) {
        if ( !$a || $a && $a === $this->v[ $rule ] ) {
            return false;
        }
        return __( 'The value has to match the previous field', 'fcpfo' ); // ++ can add the title / placeholder here
    }
    
    private function test_maxSymbols($rule, $a) {
        if ( strlen( $a ) <= $rule ) { return false; }
        return sprintf(
            __( 'The maximum content length is %s symbols, your current content is %s', 'fcpfo' ),
            $rule,
            strlen( $a )
        );
    }
    
    private function test_minSymbols($rule, $a) {
        if ( strlen( $a ) >= $rule ) { return false; }
        return sprintf(
            __( 'The minimum content length is %s symbols, your current content is %s', 'fcpfo' ),
            $rule,
            strlen( $a )
        );
    }
    
    private function test_maxLetters($rule, $a) {
        $len = strlen( $this->htmlToText( $a ) );
        if ( $len <= $rule ) { return false; }
        return sprintf(
            __( 'The maximum text length is %s symbols, your current content is %s', 'fcpfo' ),
            $rule,
            $len
        );
    }
    
    private function test_minLetters($rule, $a) {
        $len = strlen( $this->htmlToText( $a ) );
        if ( $len >= $rule ) { return false; }
        return sprintf(
            __( 'The minimum text length is %s symbols, your current content is %s', 'fcpfo' ),
            $rule,
            $len
        );
    }
    
    private function test_rscaptcha($rule, $a) {
        if ( !$rule || !class_exists( 'ReallySimpleCaptcha' ) ) { return false; }
        if ( !$a ) return __( 'Please fill in the symbols from the picture', 'fcpfo' );
        $b = new ReallySimpleCaptcha();
        $prefix = $_POST[ $rule . '_prefix' ];
        $result = $b->check( $prefix, $a );
        //$b->remove( $prefix ); // there might be a second check on authenticate filter, so just clear by cleanup()
        //++add this as the json parameter - to skip remove()
        $b->cleanup();
        if ( $result ) { return false; }
        return __( 'The entered symbols are not correct', 'fcpfo' );
    }
    
// -----____--____FILES VALIDATION____----____---____

    private function test_file_notEmpty($rule, $a) {
        return $this->test_notEmpty( $rule, $a['name'] );
    }

    private function test_file_maxSize($rule, $a) {
        if ( empty( $a['name'] ) ) {
            return false;
        }
        if ( is_numeric( $rule ) && $a['size'] < $rule ) {
            return false;
        }
        return sprintf( __( 'The file %s is too big. Max size is %s', 'fcpfo' ), '<em>'.$a['name'].'</em>', $rule );
    }
    
    private function test_file_extension($rule, $a) {
        if ( empty( $a['name'] ) ) {
            return false;
        }
        $ext = pathinfo( $a['name'], PATHINFO_EXTENSION );
        if ( is_array( $rule ) && in_array( $ext, $rule ) ) {
            return false;
        }
        return sprintf( __( 'The file %s extension doesn\'t fit the allowed list: %s', 'fcpfo' ), '<em>'.$a['name'].'</em>', implode( ', ', $rule ) );
    }
    
    private function test_file_default($a) { // this one goes silently with current server settings
        if ( !empty( $a['error'] ) ) {
            return [ // taken from the php reference for uploading errors
                0 => 'There is no error, the file uploaded with success', // doesn't count anyways
                1 => 'The uploaded file '.$a['name'].' exceeds the upload_max_filesize directive in php.ini',
                2 => 'The uploaded file '.$a['name'].' exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                3 => 'The uploaded file '.$a['name'].' was only partially uploaded',
                4 => 'No file was uploaded',
                6 => 'Missing a temporary folder',
                7 => 'Failed to write file '.$a['name'].' to disk.',
                8 => 'A PHP extension stopped the file '.$a['name'].' upload.',
            ][ $a['error'] ];
        }
        return false;
    }

// ---________---___--__________---

    private function checkValues() {

        foreach ( $this->s->fields as $f ) {

            if ( !isset( $f->validate ) || !isset( $f->name ) || !$f->name ) { continue; }

            foreach ( $f->validate as $mname => $rule ) {
                $method = 'test_' . ( $f->type == 'file' ? 'file_' : '' ) . $mname;
                $test = false;

                if ( !method_exists( $this, $method ) ) { continue; }

                if ( $f->type === 'file' ) { // ++the following can be better

                    if ( isset( $f->multiple ) && $f->multiple ) {

                        $mflip = FCP_Forms__Files::flip_files( $this->v[ $f->name ] );
                        foreach ( $mflip as $v ) {
                            if ( $this->addResult( $method, $f->name, $rule, $v ) ) {
                                $this->files_failed[ $f->name ][] = $v['name'];
                            }
                        }

                    } else {

                        if ( isset ( $this->v[ $f->name ] ) && $this->addResult( $method, $f->name, $rule, $this->v[ $f->name ] ) ) {
                            $this->files_failed[ $f->name ][] = $this->v[ $f->name ]['name'];
                        }
                    }
                    
                    continue;
                }
                
                // not file
                if ( isset( $f->multiple ) && $f->multiple ) {

                    foreach ( $this->v[ $f->name ] as $v ) {
                        $this->addResult( $method, $f->name, $rule, $v );
                    }
                    
                } else {

                    $this->addResult( $method, $f->name, $rule, ( empty( $this->v[ $f->name ] ) ? '' : $this->v[ $f->name ] ) );
                }
            }
        }
        
        // a warning, dependent on other fields warnings
        $this->unitedWarns();
    }
    
    private function addResult($method, $name, $rule, $a) {
        if ( $test = $this->{ $method }( $rule, $a ) ) {
            $this->result[$name][] = $test;
            return true;
        }
    }
    
    public function add_result($field, $value) { // add custom warning by field name for external usage
        $this->result[$field][] = $value;
        $this->unitedWarns();
    }
    
    private function unitedWarns() {
        foreach ( $this->s->fields as $f ) {
            if ( empty( $f->validate->unitedWarn ) || $this->result === null ) { continue; }
            if ( !empty( $this->result[ $f->name ] ) ) { continue; } // a button can have only 1 warn, which is united
            if ( empty( array_intersect( array_keys( $this->result ), $f->validate->unitedWarn ) ) ) { continue; }
            $this->result[ $f->name ][] = __( 'Some fields are not filled correctly', 'fcpfo' );
        }
    }
    
    private function htmlToText($a) {
        $a = preg_replace( '/</', ' <', $a ); // possible gaps to spaces
        $a = preg_replace( '/<(head|script|style|template)[^>]*>(?:.*?)<\/\1>/si', ' ', $a ); // remove the hidden tags
        $a = strip_tags( $a );
        $a = preg_replace( ['/\&nbsp;/', '/\s+/'], ' ', $a );
        $a = trim( $a );
        return $a;
    }

}
