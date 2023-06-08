<?php
/*
Process the form data
*/

if ( !$_POST['place'] && !$_POST['specialty'] ) { return; }

$url = get_site_url() . '/kliniken/';

if ( $_POST['place'] ) {
    $url = add_query_arg( 'place', urlencode( $_POST['place'] ), $url );
}

if ( $_POST['specialty'] ) {
    $url = add_query_arg( 'specialty', urlencode( $_POST['specialty'] ), $url );
}

$redirect = $url;