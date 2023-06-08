<?php
/*
Print something else instead of the form
*/


if ( !isset( $_GET['success'] ) && !isset( $_GET['fail'] ) ) { return; }

unset( $json->fields );

if ( isset( $_GET['success'] ) ) {
    $override = '<h2 style="text-align:center">' . __( 'Your message is sent successfully!', 'fcpfo' ) . '</h2>';
}

if ( isset( $_GET['fail'] ) ) {
    $override  = '<h2 style="text-align:center">' . __( 'An error appeared, the message is not sent.', 'fcpfo' ) . '</h2>';
    $override .= '<p style="text-align:center">' . sprintf( __( 'Please contact directly by %s', 'fcpfo' ), 'kontakt@klinikerfahrungen.de' ) . '</p>';
}