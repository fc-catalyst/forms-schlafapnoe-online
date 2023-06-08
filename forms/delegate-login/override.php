<?php
/*
Print something else instead of the form
*/

if ( is_user_logged_in() ) {
    $override  =
        '<div class="logged-in-message">' .
        sprintf( __( 'Hello, %s', 'fcpfo' ), '<a href="' . get_edit_profile_url() . '"><strong>' . wp_get_current_user()->display_name . '</strong></a>' ) .
        '</div>';
    return;
}
