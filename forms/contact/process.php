<?php
/*
Process the form data
*/

if ( $warning || !empty( $warns->result ) ) { return; }

require_once __DIR__ . '/../../mail/mail.php';

if ( FCP_FormsMail::to_moderator_custom( $_POST ) ) {
    $redirect = add_query_arg( 'success', '', get_permalink() );
    return;
}
$redirect = add_query_arg( 'fail', '', get_permalink() );