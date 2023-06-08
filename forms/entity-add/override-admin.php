<?php
/*
Modify the values before printing to inputs
*/

if ( !in_array( get_post( $_GET['post'] )->post_type, ['clinic', 'doctor'] ) ) { return; } //++ move it to the class

// options for select / datalist
/* old option to add specialty on the fly
global $wpdb;
$options = $wpdb->get_col( '
    SELECT `meta_value`
    FROM `'.$wpdb->postmeta.'`
    WHERE `meta_key` = "entity-specialty" AND `meta_value` <> ""
    GROUP BY `meta_value` ASC
');
FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-specialty', 'options', $options );
//*/