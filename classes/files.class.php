<?php
/*
    Operations with files and directories
*/
class FCP_Forms__Files {

    private $s, $f, $w; // json structure; $_FILES; warnings; directories ['field-name' => 'dir']
    public $files, $uploaded, $warns; // [] of prepared $_FILES with ['field']; [] of uploaded files ['name','field']

    public function __construct($s, $f, $w = []) {

        $this->s = $s;
        $this->s->fields = FCP_Forms::flatten( $s->fields );
        $this->w = $w;
        $this->f = array_map( 'self::flip_files', $f );
        $this->prepare_files();

    }

    private function prepare_files() {

        // filter $_FILES to $this->files
        
        // by structure: field exists, is multiple
        $multi = [];
        foreach ( $this->s->fields as $v ) {
            if ( $v->type !=='file' ) {
                continue;
            }
            $multi[ $v->name ] = isset( $v->multiple ) && $v->multiple ? 1 : 0;
        }

        $f = $this->f;
        foreach ( $f as $k => $v ) {
            if ( !isset( $multi[$k] ) ) { // field doesn't exist in structure
                unset( $f[$k] );
                continue;
            }

            if ( isset( $v[0] ) && !$multi[$k] ) { // field is not multiple in structure
                $f[$k] = $v[0];
                continue;
            }
            if ( isset( $v['name'] ) && $multi[$k] ) { // field is multiple in structure
                unset( $f[$k] );
                $f[$k] = [ 0 => $v ];
            }
        }

        // flatten
        $fl = []; // [0] => [ name, tmp_name, size, error, added field name ]
        foreach ( $f as $k => $v ) {
            if ( isset( $v['name'] ) ) {
                $fl[] = $v + ['field' => $k];
                continue;
            }
            foreach ( $v as $w ) {
                $fl[] = $w + ['field' => $k];
            }
        }
        $f = $fl;
        unset( $fl );

        // by server error
        foreach ( $f as $k => $v ) {
            if ( $v['error'] ) {
                unset( $f[$k] );
            }
        }

        // by warnings
        foreach ( $f as $k => $v ) {
            if ( empty( $this->w[ $v['field'] ] ) ) { // no warnings for the field
                continue;
            }
            if ( !in_array( $v['name'], $this->w[ $v['field'] ] ) ) { // no warnings for the file by name
                continue;
            }
            unset( $f[$k] );
        }
        
        // sanitize files names
        foreach ( $f as &$v ) {
            $v['name'] = sanitize_file_name( $v['name'] );
        }

        $this->files = array_values( $f ); // the list of files ready for uploading
        
        // clean tmp dir (can, probably, move to the main forms class
        self::tmp_clean();
    }

    public function upload_tmp() { // ++ can unite with upload with no arguments and tmp_main for better ui
        foreach ( $this->s->fields as $v ) {
            if ( $v->type !=='file' ) { continue; }
            if ( !self::tmp_dir()['base'] ) { continue; }

            $dirs[ $v->name ] = self::tmp_dir()['dir'] . '/' . $v->name;
        }
        return $this->upload( $dirs );
    }

    public function upload($dirs = []) { // [ field => dir, field => dir ]
        if ( empty( $dirs ) ) { return; }
        $this->warns = [];

        foreach ( $this->s->fields as $k => $v ) { // mk dirs
            if ( $v->type !=='file' ) { continue; }

            if ( !isset( $dirs[ $v->name ] ) ) {
                $this->warns[ $v->name ][] = sprintf( __( 'The folder for %s is not assigned', 'fcpfo' ), $v->name );
                unset( $this->s->fields[ $k ] );
                continue;
            }
            if ( !is_dir( $dirs[ $v->name ] ) ) {
                if ( !mkdir( $dirs[ $v->name ], 0777, true ) ) {
                    $this->warns[ $v->name ][] = sprintf( __( 'The folder for %s can not be created due to a server error', 'fcpfo' ), $v->name );
                    unset( $this->s->fields[ $k ] );
                    continue;
                }
            }
        }

        $this->uploaded_files_get( $dirs ); // get & clear the list of uploaded files

        foreach ( $this->files as $k => $v ) { // upload new files
            if ( !move_uploaded_file( $v['tmp_name'], $dirs[ $v['field'] ] . '/' . $v['name'] ) ) {
                $this->warns[ $v['field'] ][] = sprintf( __( '%s is not uploaded due to a server error', 'fcpfo' ), $v['name'] );
                continue;
            }

            // change the list of uploaded files and files
            foreach ( $this->uploaded as &$w ) { // move re-uploaded file to bottom & remove the old copy
                if ( $w['name'] === $v['name'] && $w['field'] === $v['field'] ) {
                    $w = null;
                }
            }
            $this->uploaded[] = [ 'name' => $v['name'], 'field' => $v['field'] ];
            unset( $this->files[$k] );
        }
        $this->uploaded = array_values( array_filter( $this->uploaded ) );
        
        // remove the files out of limit (single < 2 && with limit < 10 default)
        $count = []; // number of files per field
        $this->uploaded = array_reverse( $this->uploaded );
        foreach ( $this->s->fields as $v ) {
            if ( $v->type !=='file' ) { continue; }
            
            $count[ $v->name ] = 0;
            if ( !isset( $v->limit ) ) {
                $v->limit = 10;
            }
            if ( !isset( $v->multiple ) ) {
                $v->limit = 1;
            }
            foreach ( $this->uploaded as &$w ) {
                if ( $w['field'] === $v->name ) {
                    $count[ $v->name ]++;
                    if ( $v->limit && $count[ $v->name ] > $v->limit ) { // 0 is for infinite
                        $w = null;
                    }
                }
            }
        }
        $this->uploaded = array_values( array_filter( array_reverse( $this->uploaded ) ) );

        $this->uploaded_files_set(); // set globals for printing the uploaded list to the form

        return empty( $this->warns );
    }
    
    public function upload_tmp_main($dirs = []) { // [ field => dir, field => dir ] ++ can go to upload() top
        if ( empty( $dirs ) ) { return; }
        $this->warns = []; // only server errors

        foreach ( $this->s->fields as $v ) { // mk dirs
            if ( $v->type !=='file' ) { continue; }
            if ( !isset( $dirs[ $v->name ] ) ) { continue; }

            if ( !is_dir( $dirs[ $v->name ] ) ) {
                if ( !mkdir( $dirs[ $v->name ], 0777, true ) ) {
                    $this->warns[ $v->name ][] = sprintf( __( 'The folder %s can not be created due to a server error', 'fcpfo' ), $v->name );
                    return;
                }
            }
        }
        
        foreach ( $this->uploaded as $v ) { // copy the files
            
            if ( !copy(
                self::tmp_dir()['dir'] . '/' . $v['field'] . '/' . $v['name'],
                $dirs[ $v['field'] ] . '/' . $v['name']
            ) ) {
                $this->warns[ $v['field'] ][] = sprintf( __( '%s file moving failed due to a server error', 'fcpfo' ), $v['name'] );
                continue;
            }
        }
        
        if ( empty( $this->warns ) ) {
            self::rm_dir( self::tmp_dir()['dir'] );
        }
        
        return empty( $this->warns );

    }

    private function uploaded_files_get($dirs = []) {

        $result = [];
        $keep = [];

        foreach ( $this->s->fields as $v ) {
            if ( $v->type !== 'file' ) { continue; }
            if ( empty( $_POST[ $v->name . '--uploaded' ] ) ) { continue; }
            if ( $_POST[ $v->name . '--uploaded' ][0] == '' ) { continue; }

            foreach ( $_POST[ $v->name . '--uploaded' ] as $w ) {
                $w = sanitize_file_name( $w );
                
                $path = $dirs[ $v->name ] . '/' . $w;
                if ( !is_file( $path ) ) { continue; }

                $result[] = [ 'name' => $w, 'field' => $v->name ];
                $keep[ $path ] = true;

                if ( !$v->multiple ) { continue 2; }
            }

        }

        $this->uploaded = $result; // the list of uploaded files

        // delete files, not on the list, from server
        foreach ( $this->s->fields as $v ) {
            if ( $v->type !=='file' ) { continue; }

            $dir = $dirs[ $v->name ];
            $files = array_diff( scandir( $dir ), [ '.', '..' ] );

            foreach ( $files as $file ) {
                if ( $keep[ $dir . '/' . $file ] ) { continue; }
                unlink( $dir . '/' . $file );
            }
        }
    }

    private function uploaded_files_set() {
        foreach ( $this->s->fields as $v ) {
            if ( $v->type !=='file' ) { continue; }
            $_POST[ $v->name . '--uploaded' ] = [];
        }
        foreach ( $this->uploaded as $v ) {
            $_POST[ $v['field'] . '--uploaded' ][] = $v['name'];
        }
    }

    public function format_for_storing() {
        $result = [];

        foreach ( $this->uploaded as $v ) {
            $result[ $v['field'] ][] = $v['name'];
/*
            $multiple = false;
            foreach ( $this->s->fields as $w ) {
                if ( $w->name == $v['field'] && $w->multiple ) {
                    $multiple = true;
                    continue;
                }
            }

            if ( $multiple ) {
                $result[ $v['field'] ][] = $v['name'];
                continue;
            }
            $result[ $v['field'] ] = $v['name'];
//*/

        }

        return $result;
    }
    

//--_____---____--__________--Helpers

    public static function rm_dir($dir) { // from https://www.php.net/manual/ru/function.rmdir.php
        if ( !is_dir( $dir ) ) {
            return;
        }
        $files = array_diff( scandir( $dir ), [ '.', '..' ] );
        foreach ( $files as $file ) {
            $rm = $dir . '/' . $file;
            if ( is_dir( $rm ) ) {
                self::rm_dir( $rm );
                continue;
            }
            unlink( $rm );
        }
        return rmdir($dir);
    }

    public static function rm($a) {
        if ( is_file( $a ) ) {
            unlink( $a );
            return;
        }
        if ( is_dir( $a ) ) {
            self::rm_dir( $a );
        }
    }
    
    // flip the array of uploading files from [name][0] to [0][name]
    public static function flip_files($mfile = []) {
        if ( !is_array( $mfile['name'] ) ) {
            return $mfile;
        }
        $mflip = [];
        for ( $i = 0, $j = count( $mfile['name'] ); $i < $j; $i++ ) {
            foreach ( $mfile as $k => $v ) {
                $mflip[$i][$k] = $mfile[$k][$i];
            }
        }
        return $mflip;
    }
    
    public static function tmp_dir() {
        $uploads = wp_get_upload_dir()['basedir'];
        $base = empty( $_POST['fcp-form--tmpdir'] ) ? 'aa' : $_POST['fcp-form--tmpdir'];
        return [
            'main' => $uploads . '/' . FCP_Forms::$tmp_dir,
            'dir' => $uploads . '/' . FCP_Forms::$tmp_dir . '/' . $base,
            'base' => $base,
        ];
    }
    
    public static function tmp_clean() {
        $main_dir = self::tmp_dir()['main'];
        $tmp_dirs = array_diff( scandir( $main_dir ), [ '.', '..' ] );
        $rm_time = time() - 15 * 60;

        foreach ( $tmp_dirs as $tmp_dir ) {
            $rm = $main_dir . '/' . $tmp_dir;
            if ( is_dir( $rm ) && $rm_time > filectime( $rm ) ) {
                self::rm_dir( $rm );
            }
        }
    }

}
