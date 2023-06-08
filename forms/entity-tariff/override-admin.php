<?php
/*
Modify the values before printing to inputs
*/

FCP_Forms::tz_set(); // set utc timezone for all the time operations, in case the server has a different settings

require 'variables.php';


$init_values = $values;

// no tariff manipulations with no billing method picked
if ( !get_post_meta( $_GET['post'], 'entity-billing', true ) && !$admin_am ) {

    $tariff = FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-tariff' );
    $tariff->roles_view = ['administrator', 'entity_delegate'];

    $this->s->fields = [];

    array_push( $this->s->fields, $tariff );
    array_push( $this->s->fields, (object) [
        'type' => 'notice',
        'text' => '<p>' .
            sprintf(
                __( 'To apply a different tariff, please select a billing details in the field above. Or fill in a new billing information <a href="%s" target="_blank">here</a> first.', 'fcpfo-et' ),
                '/wp-admin/post-new.php?post_type=billing'
                ) .
            '</p>',
        'meta_box' => true,
    ]);
    return;
}

/*
require_once __DIR__ . '/../../mail/mail.php';
$print = FCP_FormsMail::to_accountant( 'request', $_GET['post'] );

//$print = get_option( 'wp_mail_smtp' );

//$print = get_option( 'wp_mail_smtp' );
//$print['smtp']['pass'] = base64_decode( $print['smtp']['pass'] );

//$print = class_exists( '\WPMailSMTP\Helpers\Crypto' );

//$print =  new \WPMailSMTP\Helpers\Crypto;
//$print = $print::decrypt( get_option( 'wp_mail_smtp' )['smtp']['pass'] );

//$print[] = [ get_locale(), get_user_locale(), determine_locale() ]; // website front, user / admin, currently used of <
//$print[] = __( 'The next tariff', 'fcpfo-et' );
//$print[] = switch_to_locale( 'de_DE' );
//$print[] = __( 'The next tariff', 'fcpfo-et' );
//$print[] = load_textdomain( 'fcpfo-et', __DIR__ . '/languages/fcpfo-et-de_DE.mo' );
//$print[] = __( 'The next tariff', 'fcpfo-et' );
//$print[] = restore_previous_locale();
//$print[] = __( 'The next tariff', 'fcpfo-et' );

//$print[] = __( 'View' );
//$print[] = switch_to_locale( 'de_DE' );
//$print[] = __( 'View' );
//$print[] = restore_previous_locale();
//$print[] = __( 'View' );


array_push( $this->s->fields, (object) [
    'type' => 'notice',
    'text' => '<pre>**'.print_r( $print, true ).'**</pre>',
    'meta_box' => true,
]);
//*/


// print field-by-field conditionally


// main tariff picker
if ( !$admin_am && $tariff_paid ) { // only the free tariff can be changed by a user
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff', 'roles_view', ['entity_delegate'] );
}


// tariff due date
if ( $admin_am ) { // format for the input
    $values['entity-tariff-till'] = $values['entity-tariff-till'] > $time_local
        ? date( 'd.m.Y', $values['entity-tariff-till'] )
        : '';

} else {

    if ( $tariff_paid && $values['entity-payment-status'] === 'payed' ) {
        // human readable format & styling; can just comment if too complex
        $values['entity-tariff-till'] = $time_label( $values['entity-tariff-till'], $tariff_ends_in < $prolong_gap );
    } else {
        // hide
        FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-tariff-till', [], 'unset' );
    }
}


// timezones
if ( $admin_am ) { // ++allow users to change zones before payed in future, when not one country coverage
    // make the list of timezones
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-timezone', 'options', $timezones );
}


// prolong
if ( $prolong_allowed ) {

    // activate and pre-fill the -next fields
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff-next', 'type', 'select' );
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff-next', 'options', $tariffs );
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff-next', 'value', $tariff_default );

    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-payment-status-next', 'type', 'select' );
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-payment-status-next', 'options',
        (array) FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-payment-status', 'options' ) // ++
    );

    if ( !$admin_am && $tariff_paid_next ) { // only the free tariff can be changed by a user
        FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff-next', 'roles_view', ['entity_delegate'] );
    }
}



// helping text labels

//*

if ( $admin_am ) {

    if ( !$tariff_paid ) {
        FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-tariff', [
            'type' => 'notice',
            'text' => '<strong>'.__( 'The following fields effect only paid tariffs.', 'fcpfo-et' ).'</strong>',
            'meta_box' => true,
        ], 'after' );
    }

    // date picker helping functions
    $one_year_from_now_plus_one_day = date( 'd.m.Y', strtotime( '+1 year', $time_local ) );
    FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-tariff-till', [
        'type' => 'notice',
        'text' => '<a href="#" id="one-year-ahead" style="margin-top:-12px">'.__( 'Set 1 year from now', 'fcpfo-et' ).'</a><script>
            jQuery( \'#one-year-ahead\' ).click( function( e ) {
                e.preventDefault();
                jQuery( \'#entity-tariff-till_entity-tariff\' ).val( \'' . $one_year_from_now_plus_one_day . '\' );
            });
        </script>',
        'meta_box' => true,
    ], 'after' );

    if ( $prolong_allowed ) {
        FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-tariff-next', [
            'type' => 'notice',
            'text' =>
                '<strong>' .
                sprintf( __( 'The next tariff option is available to users %s days before the current paid tariff ends.', 'fcpfo-et' ), $prolong_gap / $day ) .
                '</strong>' .
                '<span>' .
                __( 'If current tariff is free, you can schedule the paid one by picking a future date in the "Active till" field.', 'fcpfo-et' ).
                '</span>',
            'meta_box' => true,
        ], 'before' );
    }
    
    // a minor simplifying the interface
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-payment-status', 'title', '', true );
    FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-payment-status-next', 'title', '', true );
}


if ( !$admin_am ) {

    // the payment status
    if ( $tariff_paid ) {

        if ( $values['entity-payment-status'] === 'pending' ) {
            $status_message = '<em>'.
            __( 'Payment status', 'fcpfo-et' ).': ' .
            __( 'Pending', 'fcpfo-et' ).'. </em>' .
            sprintf(
                __( 'You will be billed in a few days via your mentioned billing email <em>%s</em>. For any questions or problems with receiving the bill, please contact our accountant <a href="%s">%s</a>.', 'fcpfo-et' ),
                $billing_email, $accountant_email, $accountant_email
            );

        } elseif ( $values['entity-payment-status'] === 'billed' ) {
            $status_message = '<em><font color="#35b32d">'.
            __( 'Payment status', 'fcpfo-et' ).': ' .
            __( 'Billed', 'fcpfo-et' ).'. </font></em>' .
            sprintf(
                __( 'Please check your billing email <em>%s</em> and pay the bill to activate the tariff. For any questions please contact our accountant by <a href="%s">%s</a>', 'fcpfo-et' ),
                $billing_email, $accountant_email, $accountant_email
            );

        }
        
        if ( $status_message ) {
            FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-payment-status', [
                'type' => 'notice',
                'text' => '<div>'.$status_message.'</div>',
                'meta_box' => true,
            ], 'override' );
            unset( $status_message );
        }

    }

    if ( $tariff_paid_next && $prolong_allowed ) {

        if ( $values['entity-payment-status-next'] === 'pending' ) {
            $status_message = '<em>'.
            __( 'Payment status', 'fcpfo-et' ).': ' .
            __( 'Pending', 'fcpfo-et' ).'. </em>' .
            sprintf(
                __( 'You will be billed in a few days via your mentioned billing email <em>%s</em>. For any questions or problems with receiving the bill, please contact our accountant <a href="%s">%s</a>.', 'fcpfo-et' ),
                $billing_email, $accountant_email, $accountant_email
            );

        } elseif ( $values['entity-payment-status-next'] === 'billed' ) {
            $status_message = '<em><font color="#35b32d">'.
            __( 'Payment status', 'fcpfo-et' ).': ' .
            __( 'Billed', 'fcpfo-et' ).'. </font></em>' .
            sprintf(
                __( 'Please check your billing email <em>%s</em> and pay the bill to activate the tariff. For any questions please contact our accountant by <a href="%s">%s</a>', 'fcpfo-et' ),
                $billing_email, $accountant_email, $accountant_email
            );

        } elseif ( $values['entity-payment-status-next'] === 'payed' ) {
            $status_message = '<em>'.
            __( 'Payment status', 'fcpfo-et' ).': ' .
            __( 'Payed', 'fcpfo-et' ).'. </em>';

        }
        
        if ( $status_message ) {
            FCP_Forms::json_field_by_sibling( $this->s->fields, 'entity-payment-status-next', [
                'type' => 'notice',
                'text' => '<div>'.$status_message.'</div>',
                'meta_box' => true,
            ], 'override' );
            unset( $status_message );
        }
    }

}


//if ( $prolong_allowed && $tariff_paid && $tariff_paid_active ) {
if ( $init_values['entity-tariff-till'] ) {
    $tariff_next_start = date( $date_format, $init_values['entity-tariff-till'] + $day );
    $tariff_next_start_label = '<font color="#35b32d" style="white-space:nowrap">'.$tariff_next_start.'</font>';
/*
    $till_next = $init_values['entity-tariff-till'] - $time - $values['entity-timezone-bias'];
    if ( $till_next > $day ) {
        $till_next = round( $till_next / 60 / 60 / 24 ) . ' day(s)';
    } elseif ( $till_next <= $day && $till_next > 3600 ) {
        $till_next = round( $till_next / 60 / 60 ) . ' hour(s)';
    } elseif ( $till_next <= 3600 ) {
        $till_next = round( $till_next / 60 ) . ' minute(s)';
    }
//*/
    //$scheduled_to = date( 'd.m.Y H:i:s', wp_next_scheduled( 'fcp_forms_entity_tariff_prolong' ) );
    //$and_now_is = date( 'd.m.Y H:i:s', time() );
    //$the_event = wp_get_scheduled_event( 'fcp_forms_entity_tariff_prolong' );
    
    array_push( $this->s->fields, (object) [
        'type' => 'notice',
        'text' => '<p>' .
        sprintf(
            __( 'The next tariff will be activated on %s, 00:00 local time.', 'fcpfo-et' ),
            $tariff_next_start_label
        ) .
        '</p>',
        // $tariff_next_start_label
        // <br>in '.$till_next.'<br> '.$scheduled_to.' <br>now: '.$and_now_is.' <br><pre>'.print_r( $the_event, true ).'</pre>
        'meta_box' => true,
    ]);
}

array_push( $this->s->fields, (object) [
    'type' => 'notice',
    'text' => '<p>' .
    sprintf(
        __( 'For more information check out our tariff prices and conditions <a href="%s" target="_blank">here</a>.', 'fcpfo-et' ), 
        '/preise-eintragung/'
    ) .
    '</p>',
    'meta_box' => true,
    'roles_view' => ['entity_delegate'],
]);

// just small clearing in case the billing form with step3 was opened, but was not filled on the front-end
delete_post_meta( $_GET['post'], 'entity-tariff-tmp' );

FCP_Forms::tz_reset();