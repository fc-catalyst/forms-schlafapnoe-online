<?php
/*
 * variables for further use and $value modifications
*/

if ( !isset( $values ) ) { return; }


// timing variables
$day = DAY_IN_SECONDS;
$prolong_gap = $day * 30; // a time period, when prolongation option becomes available
$billed_flush_gap = $day * 30; // a time period to pay the bill - flushes the tariff to a free one when ends


// roles
$admin_am = current_user_can( 'administrator' );

// times
$time = time();
$time_bias = $values['entity-timezone-bias'] ? $values['entity-timezone-bias'] : 0;
$time_local = $time + $time_bias;
$date_format = get_option( 'date_format' );

// tariffs
$tariffs = (array) FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff', 'options' );
$tariff_default = FCP_Forms::json_attr_by_name( $this->s->fields, 'entity-tariff', 'value' );

$values['entity-tariff'] = $values['entity-tariff'] && $tariffs[ $values['entity-tariff'] ]
                         ? $values['entity-tariff']
                         : $tariff_default;
$values['entity-tariff-till'] = $values['entity-tariff-till'] ? $values['entity-tariff-till'] : 0;
                         
$tariff_paid = $values['entity-tariff'] !== $tariff_default; // on page && payed status is added
$tariff_ends_in = $values['entity-tariff-till'] - $time_local;

$tariff_paid_active = $tariff_paid && $tariff_ends_in > 0 && $values['entity-payment-status'] === 'payed';

// prolong
$prolong_allowed = $tariff_paid && $tariff_paid_active && $tariff_ends_in < $prolong_gap || $admin_am;
if ( $prolong_allowed ) {

    $values['entity-tariff-next'] = $values['entity-tariff-next'] && $tariffs[ $values['entity-tariff-next'] ]
                                ? $values['entity-tariff-next']
                                : $tariff_default;

    $tariff_paid_next = $values['entity-tariff-next'] !== $tariff_default;
}

$dmy_to_dayend_timestamp = function( $date ) { // dmy to timestamp of the end of the day
    $date = trim( $date );
    if ( $date && preg_match( '/^\d{1,2}\.\d{1,2}\.\d{2,4}$/', $date ) ) {
        if ( $d = DateTime::createFromFormat( 'd.m.y H:i:s', $date . ' 23:59:59', new DateTimeZone( 'UTC' ) ) ) {
            return $d->getTimestamp();
        }
        if ( $d = DateTime::createFromFormat( 'd.m.Y H:i:s', $date.' 23:59:59', new DateTimeZone( 'UTC' ) ) ) {
            return $d->getTimestamp();
        }
    }
    return 0;
};

$timezones = DateTimeZone::listIdentifiers( DateTimeZone::ALL );
$timezones = (object) array_combine( $timezones, $timezones );

// for printing only
if ( !empty( $_POST ) ) { return; }

$time_label = function( $timestamp, $highlight = false ) use ( $tariff_ends_in, $date_format ) {

    if ( !isset( $tariff_ends_in ) ) { return; }

    if ( $timestamp === 0 ) {
        $return = __( 'Not set', 'fcpfo' );
    }

    $formatted = date( $date_format ? $date_format : get_option( 'date_format' ), $timestamp );

    if ( $tariff_ends_in < 0 ) { // outdated
        $return = sprintf( __( 'Ended on %s', 'fcpfo' ), $formatted );
        
    } elseif ( $tariff_ends_in < $day ) { // today
        $return = __( 'Ends today', 'fcpfo' );
        
    } elseif ( $tariff_ends_in < $day*2 ) { // tomorrow
        $return = __( 'Tomorrow is the last day', 'fcpfo' );
        
    } else {
        $return = $formatted;
    }

    if ( $highlight ) {
        return '<font color="' . ( $highlight === true ? '#b32d2e' : $highlight ) . '">' . $return . '</font>';
    }
    return $return;
};

$billing_email = get_post_meta( get_post_meta( $_GET['post'], 'entity-billing', true ), 'billing-email', true );
$accountant_email = 'buchhaltung@firmcatalyst.com';