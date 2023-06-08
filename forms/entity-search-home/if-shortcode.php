<?php

if ( !function_exists( 'fct_forms_data_group' ) ) {
    function fct_forms_data_group( $var_name, $meta_keys = [] ) {
        if ( !$var_name || !count( $meta_keys ) ) { return; }
        
        global $wpdb;
        $results = $wpdb->get_col('
            SELECT `meta_value`
            FROM `'.$wpdb->postmeta.'`
            WHERE `meta_key` = "' . implode( '" OR `meta_key` = "', $meta_keys ) . '"
            GROUP BY `meta_value`
        ');

        if ( !count( $results ) ) { return; }
        
        ?>
        <script type="text/javascript">window.fcp_forms_data.<?php echo $var_name ?> = <?php echo json_encode( $results ) ?>;</script>
        <?php
    }
}

add_action( 'wp_footer', function() {
    fct_forms_data_group( 'specialties', ['entity-specialty'] );
    fct_forms_data_group( 'places', ['entity-geo-city', 'entity-region'] );
});
