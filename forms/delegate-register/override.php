<?php
/*
Print something else instead of the form
*/
/*
if ( ( $atts['override'] === 'logged-in-registered-empty' || $atts['override'] === 'registered-empty' ) && is_user_logged_in() ) {
    $override = '';
    unset( $json->fields );
    return;
}
//*/

if ( is_user_logged_in() ) {
    $override = '';
    unset( $json->fields );
    return;
}