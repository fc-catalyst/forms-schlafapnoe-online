<?php
/*
Process the form data
*/

if ( $warning || !empty( $warns->result ) ) {
    return;
}

$params = FCP_Forms::email_to_user( $_POST['user-email'] );

$register = wp_insert_user( $params + [
	'user_pass' => $_POST['user-password'],
	'role' => 'entity_delegate'
]);

if ( is_wp_error( $register ) ) {
    foreach ( $register->errors as $v ) {
        $warns->add_result( 'user-email', implode( '<br />', $v ) );
    }
    return;
}

// set the admin colors for the user
wp_update_user([
    'ID' => $register,
    'admin_color' => 'klinikerfahrungen'
]);

// log in
$creds['user_login'] = $_POST['user-email'];//$params['user_login'];
$creds['user_password'] = $_POST['user-password'];
$creds['remember'] = false;

$user = wp_signon( $creds );

if ( is_wp_error( $user ) ) {
   $warning = $user->get_error_message();
   return;
}

$redirect = get_option( 'siteurl' ) . '/unternehmen-eintragen/?step2';