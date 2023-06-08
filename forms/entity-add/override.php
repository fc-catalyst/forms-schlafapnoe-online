<?php
/*
Print something else instead of the form
*/

if ( !is_user_logged_in() ) {
    unset( $json->fields );
    $override = '';
    return;
}


// autofill some values
$current_user = wp_get_current_user();
FCP_Forms::json_attr_by_name( $json->fields, 'entity-email', 'value', $current_user->user_email );

/* old option to add specialty on the fly
// options for select / datalist
global $wpdb;
$options = $wpdb->get_col( '
    SELECT `meta_value`
    FROM `'.$wpdb->postmeta.'`
    WHERE `meta_key` = "entity-specialty" AND `meta_value` <> ""
    GROUP BY `meta_value` ASC
');
FCP_Forms::json_attr_by_name( $json->fields, 'entity-specialty', 'options', $options );
//*/