<?php
/*
    Overall settings for the form
*/

// login only with the email (remove the username option)
// correct the text
add_filter( 'gettext',  function($translation, $text, $domain) {
    if ( $GLOBALS['pagenow'] !== 'wp-login.php' ) { return $translation; }
    if ( $text !== 'Username or Email Address' ) { return $translation; }
    return __( 'Email', $domain );
}, 10, 3);

// remove the username login authentification (keep only the email)
remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );


// styling

add_filter( 'login_headerurl', function() {
    return home_url();
});

add_filter( 'login_headertext', function() {
    if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) { return get_custom_logo(); } else {  return; }
});

add_action( 'login_enqueue_scripts', function() {
    ?>
    <style>
        :root {
            --main-color:#23667b;
        }
        .login h1 a.custom-logo-link {
            background:none!important;
            text-indent:0!important;
        }
        .login h1 a.custom-logo-link img {
            height:100%;
            width:auto;
        }
        input[type=checkbox]:focus, input[type=color]:focus, input[type=date]:focus, input[type=datetime-local]:focus, input[type=datetime]:focus, input[type=email]:focus, input[type=month]:focus, input[type=number]:focus, input[type=password]:focus, input[type=radio]:focus, input[type=search]:focus, input[type=tel]:focus, input[type=text]:focus, input[type=time]:focus, input[type=url]:focus, input[type=week]:focus, select:focus, textarea:focus {
            border-color:var(--main-color)!important;
            box-shadow:0 0 0 1px var(--main-color)!important;
        }

        .wp-core-ui .button-primary {
            background-color:var(--main-color)!important;
            border-color:var(--main-color)!important;
        }
        .wp-core-ui .button-secondary {
            color:var(--main-color)!important;
        }
        .login .button.wp-hide-pw:focus {
            border-color:var(--main-color)!important;
        }
        
        .login #backtoblog a:hover, .login #nav a:hover, .login h1 a:hover {
            color:var(--main-color)!important;
        }
        
        /* captcha */
        .captcha_wrap {
            display:flex;
            margin-bottom:15px!important;
        }
        #captcha, #captcha ~ img {
            width:50%!important;
            height:auto!important;
        }
        
    </style>
    <?php
});


// really simple captcha to login page ++move to register?
add_action( 'login_form', function() {
    if ( !class_exists( 'FCP_Forms__Draw' ) ) { return; }
    
    // get the rscaptcha field
    $json = FCP_Forms::structure( 'delegate-login' );
    if ( $json === false ) { return; }
    $json = FCP_Forms::flatten( $json->fields );
    foreach ( $json as $v ) {
        if ( $v->type !== 'rscaptcha' ) { continue; }

        unset( $v->async );

        echo '<div class="captcha_wrap">';
        FCP_Forms__Draw::rscaptcha_print( $v );
        echo '</div>';

        return;
    }

}, 9999999999 );

add_filter( 'authenticate', function($user, $username, $password) {
    
    // go on with existing filters
    if ( empty( $username ) || empty( $password ) ) { return $user; }
    if ( !class_exists( 'ReallySimpleCaptcha' ) ) { return $user; }

    // get the rscaptcha field
    $json = FCP_Forms::structure( 'delegate-login' );
    if ( $json === false ) { return $user; } // go on as no captcha field defined
    $json = FCP_Forms::flatten( $json->fields );
    $field = '';
    foreach ( $json as $v ) {
        if ( $v->type !== 'rscaptcha' ) { continue; }
        $field = $v->name;
        break;
    }
    
    $deny = function($w) {
        remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );
        remove_action( 'authenticate', 'wp_authenticate_email_password', 20 );
        return new WP_Error( 'denied', __( $w ) );
    };
    
    $value = $_POST[ $field ];
    if ( !$value ) { return $deny( 'Please fill in the Captcha field' ); }

    $b = new ReallySimpleCaptcha();
    $prefix = $_POST[ $field . '_prefix' ];
    $result = $b->check( $prefix, $value );
    $b->remove( $prefix );
    if ( $result ) { return $user; } // go on as success

    return $deny( 'The Symbols from the Captcha field do not match' );

}, 10, 3 );