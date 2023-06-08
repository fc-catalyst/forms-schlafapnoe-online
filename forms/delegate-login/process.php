<?php
/*
Process the form data
*/

if ( $warning || !empty( $warns->result ) ) {
    return;
}

// log in
$creds['user_login'] = $_POST['user-email'];
$creds['user_password'] = $_POST['user-password'];
$creds['remember'] = $_POST['rememberme'] ? true : false;

$user = wp_signon( $creds );

if ( is_wp_error( $user ) ) {
   $warning = $user->get_error_message();
   return;
}

$redirect = get_option( 'siteurl' ) . '/wp-admin/edit.php?post_type=clinic';