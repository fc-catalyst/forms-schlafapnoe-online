<?php

FCP_Forms::tz_set();

// meta select for 

if ( !class_exists( 'FCP_Add_Meta_Boxes' ) ) {
    include_once $this->self_path . 'classes/add-meta-boxes.class.php';
}
if ( !class_exists( 'FCP_Forms__Draw' ) ) {
    include_once $this->self_path . 'classes/draw-fields.class.php';
}

$json = FCP_Forms::structure( $dir );
if ( $json === false ) { return; }


new FCP_Add_Meta_Boxes( $json, (object) [
    'title' => 'Tariff',
    'text_domain' => 'fcpfo',
    'post_types' => ['clinic', 'doctor'],
    'context' => 'side',
    'priority' => 'default',
    'text_domain' => 'fcpfo-et'
] );


// datepicker
add_action( 'admin_enqueue_scripts', function() {
    global $post;
    if ( !in_array( $post->post_type, [ 'clinic', 'doctor' ] ) ) { return; }
/*
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui');
//*/
    wp_enqueue_script(
        'jquery-ui-datepicker',
        $this->self_url . 'forms/' . basename( __DIR__ ) . '/assets/jquery-ui.js'
    );
    wp_enqueue_style(
        'jquery-ui-css',
        $this->self_url . 'forms/' . basename( __DIR__ ) . '/assets/jquery-ui.css'
    );
});

add_action( 'admin_footer', function() {
    global $post;
    if ( !in_array( $post->post_type, [ 'clinic', 'doctor' ] ) ) { return; }
    ?>
    <script type="text/javascript">
        jQuery( document ).ready( function($){
            $( '#entity-tariff-till_entity-tariff' ).datepicker( {
                dateFormat : 'dd.mm.yy',
                minDate : new Date()
            });
        });
    </script>
    <?php
});


// schedule for clearing and prolonging tariffs
register_activation_hook( $this->self_path_file, function() {
    wp_clear_scheduled_hook( 'fcp_forms_entity_tariff_prolong' );
    $day_start = mktime( 0, 0, 0 );
    // hourly because of timezones; not counting not standard 45 and 30 min gaps, though, for later, maybe
    wp_schedule_event( $day_start, 'hourly', 'fcp_forms_entity_tariff_prolong' );
});

register_deactivation_hook( $this->self_path_file, function() {
    wp_clear_scheduled_hook( 'fcp_forms_entity_tariff_prolong' );
});


// sortable meta fields
add_filter( 'manage_clinic_posts_columns', function( $columns ) {
    $columns['tariff'] = __( 'Tariff', '' );
    $columns['tariff-payed'] = __( 'Payed', '' );
    $columns['tariff-till'] = __( 'Till', '' );
    unset( $columns['worthy'] ); // ++--not needed if Worthy plugin is not used
    //unset( $columns['date'] );
    return $columns;
});
add_action( 'manage_clinic_posts_custom_column' , function( $column, $post_id ) {
    switch ( $column ) {

        case 'tariff' :
            echo get_post_meta( $post_id , 'entity-tariff' , true );
            break;
        case 'tariff-payed' :
            echo get_post_meta( $post_id , 'entity-payment-status' , true );
            break;
        case 'tariff-till' :
            $till = get_post_meta( $post_id , 'entity-tariff-till' , true );
            echo $till ? date( 'd.m.Y', $till ) : 'Not set';
            break;
    }
}, 10, 2 );
add_filter( 'manage_edit-clinic_sortable_columns', function( $columns ) {
    $columns['tariff-till'] = 'tariff-till';
    return $columns;
});

add_filter( 'manage_doctor_posts_columns', function( $columns ) { // ++that's stupid to repeat - just use namespace and functions
    $columns['tariff'] = __( 'Tariff', '' );
    $columns['tariff-payed'] = __( 'Payed', '' );
    $columns['tariff-till'] = __( 'Till', '' );
    unset( $columns['worthy'] ); // ++--not needed if Worthy plugin is not used
    //unset( $columns['date'] );
    return $columns;
});
add_action( 'manage_doctor_posts_custom_column' , function( $column, $post_id ) {
    switch ( $column ) {

        case 'tariff' :
            echo get_post_meta( $post_id , 'entity-tariff' , true );
            break;
        case 'tariff-payed' :
            echo get_post_meta( $post_id , 'entity-payment-status' , true );
            break;
        case 'tariff-till' :
            $till = get_post_meta( $post_id , 'entity-tariff-till' , true );
            echo $till ? date( 'd.m.Y', $till ) : 'Not set';
            break;
    }
}, 10, 2 );
add_filter( 'manage_edit-doctor_sortable_columns', function( $columns ) {
    $columns['tariff-till'] = 'tariff-till';
    return $columns;
});

add_action( 'pre_get_posts', function( $query ) {
    global $pagenow;
    if( !is_admin() || $pagenow !== 'edit.php' || $_GET['post_type'] !== 'clinic' && $_GET['post_type'] !== 'doctor' ) { return; }
    if( $query->get( 'orderby') !== 'tariff-till' ) { return; }

    $meta_query = [
        'relation' => 'OR',
        [
            'key' => 'entity-tariff-till',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key' => 'entity-tariff-till',
        ],
    ];
    
    //$query->set( 'meta_key', 'entity-tariff-till' );
    $query->set( 'meta_query', $meta_query );
    $query->set( 'orderby', 'meta_value_num' ); //meta_value
});


// add translation languages
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'fcpfo-et', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});



// scheduled actions
add_action( 'fcp_forms_entity_tariff_ends', function($id) { // creates / updates on paid payed save
    //require_once __DIR__ . '/../../mail/mail.php'; // ++restore in 1 year? january 2023 add condition
    //FCP_FormsMail::to_client( 'ends', $id );
}, 10, 1 );

add_action( 'fcp_forms_entity_tariff_prolong', function() {

    FCP_Forms::tz_set();

    $fields = [
        'tariff_next' => 'entity-tariff-next',
        'status_next' => 'entity-payment-status-next',
        'timezone_name' => 'entity-timezone',
    ];
    
    $get_meta = function( $field, $alias ) {
        global $wpdb;
        static $ind = -1;
        $ind++;
        return '
'.( $ind ? 'JOIN (' : 'FROM (' ).'
    SELECT
        posts.ID,
        '.( $ind ? '' : 'mt0.meta_value AS till, #entity-tariff-till' ).'
        IF ( mt4.meta_key = "'.$field.'", mt4.meta_value, NULL ) AS `'.$alias.'`
    FROM `'.$wpdb->posts.'` AS posts
        LEFT JOIN `'.$wpdb->postmeta.'` AS mt0 ON ( posts.ID = mt0.post_id )
        LEFT JOIN `'.$wpdb->postmeta.'` AS mt1 ON ( posts.ID = mt1.post_id )
        LEFT JOIN `'.$wpdb->postmeta.'` AS mt2 ON ( posts.ID = mt2.post_id AND mt2.meta_key = "entity-timezone-bias" )
        LEFT JOIN `'.$wpdb->postmeta.'` AS mt3 ON ( posts.ID = mt3.post_id )
        LEFT JOIN `'.$wpdb->postmeta.'` AS mt4 ON ( posts.ID = mt4.post_id AND mt4.meta_key = "'.$field.'" )
    WHERE
        1 = 1
        AND (
            ( mt0.meta_key = "entity-tariff-till" AND CAST( IF ( mt2.meta_key = "entity-timezone-bias", mt0.meta_value - mt2.meta_value, mt0.meta_value ) AS SIGNED ) < ' . time() . ' ) #@till_time
            AND
            ( mt1.meta_key = "entity-timezone-bias" OR mt2.post_id IS NULL )
            AND
            ( mt3.meta_key = "'.$field.'" OR mt4.post_id IS NULL )
        )
        AND posts.post_type IN ("clinic", "doctor")
    GROUP BY posts.ID
) AS sq'.$ind.'
        ';
    };

    $get_metas = function( $fields, $get_meta ) {
        $result = '';
        foreach( $fields as $alias => $field ) {
            $result .= $get_meta( $field, $alias );
        }
        return $result;
    };


    global $wpdb;
    $outdated = $wpdb->get_results( '
#SET @till_time = ' . time() . '; #it is not liked by $wpdb, or is it about ";" - dunno
SELECT sq0.ID, till, ' . implode( ', ', array_keys( $fields ) ) . '
' . $get_metas( $fields, $get_meta ) . '
ON sq0.ID = sq' . implode( '.ID AND sq0.ID = sq', array_slice( array_keys( array_values( $fields ) ), 1 ) ) . '.ID
    ');

    foreach( $outdated as $p ) {
        fcp_flush_tariff_by_id( $p );
    }

    FCP_Forms::tz_reset();
});

function fcp_tariff_filter_text($text) {
    if ( !$text ) { return ''; }
    
    // remove shortcodes
    $text = preg_replace( '/\[[\w\/][\w\d\-_]*(.*?)\]/i', '', $text );

    // ignore filters if an admin says so
    if ( fct1_meta( 'entity-ignore-content-filters' ) ) { return $text; }

    // filter links
    $tariff_running = fcp_tariff_get()->running;

    switch ( $tariff_running ) {
        case 'standardeintrag':
            $text = fct1_a_clear_all( $text, 0 );
            $text = fct1_html_words_limit( $text, 850 );
            break;
        case 'premiumeintrag':
            // ++-- the exception, which works till the older clients use old agb
            if ( fct1_meta( 'entity-old-agb' ) ) {
                return fct1_a_clear_all( $text, 2, [
                    'rel' => [
                        'internal' => '--remove',
                        'external' => 'noopener',
                    ]
                ]);
            }
            // ++/
            $text = fct1_a_clear_all( $text, 0, [
                'rel' => [
                    'internal' => '--remove',
                    'external' => 'noopener',
                ]
            ]);
            $text = fct1_html_words_limit( $text, 850 );
            break;
        default: // 'kostenloser_eintrag'
            $text = fct1_a_clear_all( $text, 0 );
            $text = fct1_html_words_limit( $text, 450 );
    }
    return $text;
}

function fcp_tariff_get() {
    $free = 'kostenloser_eintrag';
    $tariff = fct1_meta( 'entity-tariff' ) ? fct1_meta( 'entity-tariff' ) : $free;
    $status = fct1_meta( 'entity-payment-status' );
    $payed = $tariff && $tariff !== $free && $status === 'payed';

    return (object) [
        'tariff' => $tariff,
        'paid' => $tariff !== $free,
        'running' => $payed ? $tariff : $free,
        'running_paid' => $payed, //++??
    ];
}

function fcp_flush_tariff_by_id($p, &$values = []) {
    if ( !$p ) { return; }
    if ( is_array( $p ) ) { $p = (object) $p; }
    if ( is_object( $p ) && !$p->ID ) { return; }
    if ( is_numeric( $p ) ) {
        $p = (object) [
            'ID' => $p
        ];
    }
    $p->ID = (int) $p->ID; // intval()
    
    $meta_a2q_where = function($arr = null) { // ++send to a separate class for the form?
        static $arr_saved = [];
        if ( !$arr ) { return $arr_saved; }
        $arr_saved = $arr;
        if ( !$arr[0] ) { return '1=1'; } // pick all fields if no elements
        return '`meta_key` = %s' . str_repeat( ' OR `meta_key` = %s', count( $arr ) - 1 );
    };
    $meta_a2q_insert = function($arr = null) use ($p) {
        static $arr_saved = [];
        if ( !$arr ) { return $arr_saved; }
        $arr_saved = [];
        if ( empty( $arr ) ) { return; }
        foreach ( $arr as $k => $v ) { array_push( $arr_saved, $p->ID, $k, $v ); }
        return '( %s, %s, %s )' . str_repeat( ', ( %s, %s, %s )', count( $arr ) - 1 );
    };

    global $wpdb;
    
    // get values if are not provided and check, else - trust and do what has to be done
    if ( $p->ID && count( (array) $p ) === 1 ) {

        $q = $meta_a2q_where( ['entity-tariff-till', 'entity-timezone', 'entity-timezone-bias', 'entity-tariff-next', 'entity-payment-status-next'] ); // bias here to compare later*
        
        $query = 'SELECT `meta_key`, `meta_value` FROM `'.$wpdb->postmeta.'` WHERE `post_id` = %d AND ( '.$q.' )';
        $query = $wpdb->prepare( $query, array_merge( [ $p->ID ], $meta_a2q_where() ) );
        if ( $query === null ) { return; }
        
        $results = $wpdb->get_results( $query );
        foreach ( $results as $v ) { $p->{ $v->meta_key } = $v->meta_value; }
        unset( $results, $q, $query, $v );

        // check if outdated*
        $p->{ 'entity-timezone-bias' } = $p->{ 'entity-timezone-bias' } ? (int) $p->{ 'entity-timezone-bias' } : 0;
        if ( (int) $p->{ 'entity-tariff-till' } - $p->{ 'entity-timezone-bias' } >= time() ) { return; }
        
        $p->till = $p->{ 'entity-tariff-till' };
        $p->tariff_next = $p->{ 'entity-tariff-next' };
        $p->status_next = $p->{ 'entity-payment-status-next' };
        $p->timezone_name = $p->{ 'entity-timezone' };
        
    }

    // remove outdated meta
    $q = $meta_a2q_where( ['entity-tariff', 'entity-payment-status', 'entity-tariff-till', 'entity-timezone-bias', 'entity-tariff-next', 'entity-payment-status-next'] );
    $query = 'DELETE FROM `'.$wpdb->postmeta.'` WHERE `post_id` = %d AND ( '.$q.' )';
    if ( $query = $wpdb->prepare( $query, array_merge( [ $p->ID ], $meta_a2q_where() ) ) ) { $wpdb->query( $query ); }

    // prepare the updated data to insert
    $insert = [];
    if ( $p->tariff_next ) {
        $insert['entity-tariff'] = $p->tariff_next;
    }
    if ( $p->status_next ) {
        $insert['entity-payment-status'] = $p->status_next;
    }
    if ( $p->tariff_next ) {
        $insert['entity-tariff-till'] = strtotime( '+1 year', $p->till );

        $zone = new DateTimeZone( $p->timezone_name );
        $insert['entity-timezone-bias'] = $zone->getTransitions( $p->till, $p->till )[0]['offset'];
        unset( $zone );
    }
    
    // insert the updated meta
    if ( !empty( $insert ) ) {
        $query = 'INSERT INTO `'.$wpdb->postmeta.'` ( `post_id`, `meta_key`, `meta_value` ) VALUES '.$meta_a2q_insert( $insert );
        if ( $query = $wpdb->prepare( $query, $meta_a2q_insert() ) ) { $wpdb->query( $query ); }
    }
    
    // flush the post cache
    clean_post_cache( $p->ID );
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $p->ID );
    }
    
    // ++mail here?
    
    // modify the $values from the scope
    if ( empty( $values ) ) { return; }
    
    $values['entity-tariff'] = $insert['entity-tariff'] ? $insert['entity-tariff'] : '';
    $values['entity-payment-status'] = $insert['entity-payment-status'] ? $insert['entity-payment-status'] : '';
    $values['entity-tariff-till'] = $insert['entity-tariff-till'] ? $insert['entity-tariff-till'] : 0;
    $values['entity-timezone-bias'] = $insert['entity-timezone-bias'] ? $insert['entity-timezone-bias'] : 0;
    $values['entity-tariff-next'] = '';
    $values['entity-payment-status-next'] = '';

}

FCP_Forms::tz_reset();