<?php
/*
Print something else instead of the form
*/

// fill in the search values to inputs
if ( !$_GET['specialty'] && !$_GET['place'] ) { return; }

FCP_Forms::json_attr_by_name(
    $json->fields,
    'specialty',
    'value',
    $_GET['specialty'] ? htmlspecialchars( urldecode( $_GET['specialty'] ) ) : ''
);

FCP_Forms::json_attr_by_name(
    $json->fields,
    'place',
    'value',
    $_GET['place'] ? htmlspecialchars( urldecode( $_GET['place'] ) ) : $_GET['place']
);