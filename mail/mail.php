<?php

class FCP_FormsMail {

    private static function details($a = []) {

        if ( empty( self::$details ) ) {

            $url = get_bloginfo( 'wpurl' );

            self::$details = [
                'domain' => $_SERVER['SERVER_NAME'],
                'url' => $url,

                'sending' => 'kontakt@'.$_SERVER['SERVER_NAME'], // must be owned by smtp sender, if smtp
                'sending_name' => get_bloginfo( 'name' ),

                // ++add dynamic loading by role
                'accountant' => 'buchhaltung@firmcatalyst.com',
                'accountant_name' => 'Accountant',
                'accountant_locale' => 'de_DE',
                'moderator' => 'buchhaltung@firmcatalyst.com',//'kontakt@klinikerfahrungen.de',
                'moderator_name' => 'Moderator',
                'moderator_locale' => 'de_DE',
                'admin' => 'developer@firmcatalyst.com', // technical purposes
                'admin_name' => 'Admin', // technical purposes
                'admin_locale' => 'en_US',

                // tmp ++personal locales are not ready here
                'user_locale' => 'de_DE',
                'client_locale' => 'de_DE',

                'footer' => '<a href="'.$url.'" target="blank" rel="noopener noreferrer">'.$_SERVER['SERVER_NAME'].'</a>',

                'issmtp' => true,
/*
                'smtp' => [
                    'Host' => '',
                    'Port' => '',
                    'SMTPSecure' => '',
                    'SMTPAuth' => true,
                    'Username' => '',
                    'Password' => '',
                    'SMTPDebug' => true,
                ],
//*/
                'WPMailSMTP' => true, // override settings with WP Mail SMTP

                'debug' => false,
                'smtpdebug' => false,
            ];

            if ( self::$details['issmtp'] && self::$details['WPMailSMTP'] && $smtp_override = self::WPMailSMTP() ) {
                self::$details = array_merge( self::$details, $smtp_override );
            }

        }

        if ( empty( $a ) ) {
            return self::$details;
        }

        self::$details = array_merge( self::$details, $a );

    }

    private static $details = [],
                  $messages = [ // ++'user, 'admin'
        'accountant' => [
            'request' => [ // works
                'subject' => 'Paid tariff request',
                'content' => 'A paid tariff is requested. Please, bill the client and mark the status as "Billed". When the bill is payed, please remember to mark the status as "Payed" to activate the Tariff.',
                'list' => ['entity-tariff', 'billing-company', 'billing-address', 'billing-name', 'billing-email', 'billing-vat'],
            ],
            'prolong' => [
                'subject' => 'Prolongation request',
                'content' => 'A Tariff prolongation is requested. Please bill the client and mark the entity prolongation status as "Billed". When the bill is payed, please remember to mark the status as "Payed" to schedule or activate the Tariff.',
                'list' => ['entity-tariff-next', 'billing-company', 'billing-address', 'billing-name', 'billing-email', 'billing-vat'],
            ],
            'change' => [
                'subject' => 'Tariff Change request',
                'content' => 'A Tariff change is requested in terms of prolongation. Please bill the client and mark the entity prolongation status as "Billed". When the bill is payed, please remember to mark the status as "Payed" to schedule or activate the Tariff.',
                'list' => ['entity-tariff-next', 'billing-company', 'billing-address', 'billing-name', 'billing-email', 'billing-vat'],
            ],
            'cancel' => [ // chron
                'subject' => 'Bill not payed',
                'content' => 'The client has not payed the Bill in a set up period of time. You can now cancel the Bill, or contact the client directly.',
                'list' => ['billing-company', 'billing-address', 'billing-name', 'billing-email', 'billing-vat'],
            ],
        ],

        'client' => [
            'activated' => [ // payed
                'subject' => 'New tariff is activated',
                'content' => 'Your new tariff is activated.',
                'list' => ['entity-tariff'],
            ],
            'prolonged' => [ // payed
                'subject' => 'Your tariff is prolonged',
                'content' => 'Your tariff is prolonged successfully.',
                'list' => ['entity-tariff'],
            ],
            'ends' => [
                'subject' => 'Important: your listing on klinikerfahrungen.de will expire in %expire_days days',
                'heading' => 'Your Listing is expiring',
                'content' => 'you Premium-Listing on our plattform will expire in %expire_days days: %listing_link. If no action is taken, your premium entry will be automatically changed into a standard entry. This means your company description will be reduced to a maximum of 450 words and you will loose your valueable DoFollow Link.

Keeping your premium features only costs you 29€/year. In order to keep your premium listing, you need to extend the membership in your profile: %listing_edit_link.

In this tutorial we show you how to renew your listing: %tutorial_link',
            ],
            'ended' => [
                'subject' => 'Your tariff has just ended',
                'content' => 'Your tariff has just ended. Free Tariff is activated.',
            ],
        ],

        'user' => [
            'published' => [
                'subject' => 'Your entry is published',
                'content' => 'Your entry has just been published.',
                'list' => ['entity-tariff'],
            ],
        ],
        
        'moderator' => [
            'entity_added' => [ // submitted for review // works
                'subject' => 'Clinic / doctor added',
                'content' => 'A new clinic or doctor has just been added. Please check it and publish, if it is valid.',
            ],
            'entity_updated' => [ // works
                'subject' => 'Clinic / doctor changed',
                'content' => 'A client has changed some information in an entry. Please check if it is still valid.',
            ],
        ]
    ];

    // collect title && meta data && billing data by a post id or array of ids; also loads the jsons with titles
    public static function get_data($ids = [], $nocached = false, $cache = false) { 
        static $combined = [];

        if ( empty( $ids ) ) { return $combined; }

        if ( is_numeric( $ids ) ) {
            $ids = [ $ids ];
        }

        $filter_result = function( $combined ) use ( $ids ) {
            $return = [];
            foreach ( $ids as $v ) {
                $return[ $v ] = $combined[ $v ];
            }
            return $return;
        };

        if ( empty( $combined ) ) { // cache the values from the structures
            self::get_structures( 'entity-add' );
            self::get_structures( 'billing-add' );
            self::get_structures( 'entity-tariff' );
        }

        if ( !$nocached && !empty( $combined ) ) { // use cached values from $combined
            $ids_filtered = array_diff( $ids, array_keys( $combined ) );
            if ( empty( $ids_filtered ) ) {
                return $filter_result( $combined );
            }
            $ids = $ids_filtered;
        }

        global $wpdb;
        
        // select titles & authors
        $r = $wpdb->get_results( '
            SELECT `ID`, `post_author`, `post_title`
            FROM `'.$wpdb->posts.'`
            WHERE `ID` IN (' . implode( ',', $ids ) . ')
        ');
        $titles = [];
        $author_ids = [0];
        foreach ( $r as $k => $v ) {
            $titles[ $v->ID ] = $v->post_title;
            $author_ids[ $v->ID ] = $v->post_author;
        }
        
        // select meta
        $r = $wpdb->get_results( '
            SELECT `post_id`, `meta_key`, `meta_value`
            FROM `'.$wpdb->postmeta.'`
            WHERE `post_id` IN (' . implode( ',', $ids ) . ')
        ');
        // AND `meta_key` IN ( "entity-tariff", "entity-tariff-next", "entity-billing" )
        $metas = [];
        $bill_ids = [0];
        foreach ( $r as $k => $v ) {
            $metas[ $v->post_id ][ $v->meta_key ] = $v->meta_value;
            if ( $v->meta_key !== 'entity-billing' ) { continue; }
            $bill_ids[] = $v->meta_value;
        }

        // select billing meta details
        $r = $wpdb->get_results( '
            SELECT `post_id`, `meta_key`, `meta_value`
            FROM `'.$wpdb->postmeta.'`
            WHERE `post_id` IN (' . implode( ',', $bill_ids ) . ')
        ');
        $billings = [];
        foreach ( $r as $k => $v ) {
            $billings[ $v->post_id ][ $v->meta_key ] = $v->meta_value;
        }

        // select authors
        $r = $wpdb->get_results( '
            SELECT `ID`, `user_email`, `display_name`
            FROM `'.$wpdb->users.'`
            WHERE `ID` IN (' . implode( ',', $author_ids ) . ')
        ');
        $authors = [];
        foreach ( $r as $k => $v ) {
            $authors[ $v->ID ] = [ $v->user_email, $v->display_name ];
        }

        // combine && save the results
        foreach ( $titles as $k => $v ) {
            if ( $cache && $combined[ $k ] ) { // for comparison puprose: current goes to ['cached'], new replaces
                $combined[ $k ]['cached'] = $combined[ $k ];
            }
            $combined[ $k ]['title'] = $v;
            $combined[ $k ]['meta'] = array_merge(
                isset( $metas[ $k ] ) ? $metas[ $k ] : [],
                isset( $billings[ $metas[ $k ]['entity-billing'] ] ) ? $billings[ $metas[ $k ]['entity-billing'] ] : []
            );
            // metas have unique names, so who cares, that billings are along with the original metas
            $combined[ $k ]['author'] = $authors[ $author_ids[ $k ] ];
        }

        return $filter_result( $combined );
    }
    
    private static function get_structures($form = '') { // no division by forms, as metas are unique anyways
        static $titles = [];
    
        if ( !$form ) { return $titles; }
        
        $json = FCP_Forms::structure( $form );
        if ( $json === false ) { return; }
        $json = FCP_Forms::flatten( $json->fields );

        foreach ( $json as $v ) {
            $title = isset( $v->title ) ? $v->title : ( isset( $v->placeholder ) ? $v->placeholder : '' );
            if ( isset( $v->name ) && $title ) {
                $titles['titles'][ $v->name ] = $title;
            }
            if ( isset( $v->options ) ) {
                $titles['options'][ $v->name ] = $v->options;
            }
        }

        // the next-tariff has no options, it copies the tariff, so here is a crutch
        $titles['options'][ 'entity-tariff-next' ] = $titles['options'][ 'entity-tariff' ];

        return $titles;
    }

    private static function message_datalist($id, $structure = []) {
        if ( empty( $structure ) ) { return; }

        $data = self::get_data( $id );
        $structures = self::get_structures();

        $return = '';

        foreach ( $structure as $v ) {
            $title = $structures['titles'][ $v ] ? $structures['titles'][ $v ] : $v;

            $value = $data[ $id ]['meta'][ $v ] ? $data[ $id ]['meta'][ $v ] : '–';
            $value = $data[ $id ]['meta'][ $v ] &&
                     $structures['options'][ $v ] &&
                     $structures['options'][ $v ]->{ $data[ $id ]['meta'][ $v ] } ?
                $structures['options'][ $v ]->{ $data[ $id ]['meta'][ $v ] } :
                $value;

            $return .= '<li>'.$title.': <strong>'.$value.'</strong></li>';
        }

        return '<ul>' . $return . '</ul>';
    }

    // compare the __POST with older get_data and list the changed lines
    private static function message_datalist_moderator_changes($id) {

        $data = self::get_data( $id, true, true );
        
        if ( empty( $data[ $id ]['cached'] ) ) { return; }
        
        $structures = self::get_structures();
        
        $difference = [];

/*
        // this doesn't work, as the title is saved much earlier, so comparing with itself, really
        // ++can probably add an earlier number for FCP_Add_Meta_Boxes->saveMetaBoxes()
        if ( $data[ $id ]['title'] !== $data[ $id ]['cached']['title'] ) {
            $difference['entity-name'] = [
                'title' => $structures['titles']['entity-name'],
                'before' => $data[ $id ]['cached']['title'],
                'after' => $data[ $id ]['title'],
            ];
        }
//*/

        //++ I should improve to $compare = self::get_structures( 'entity-add' ); so here is a crutch of manual list of fields for comparison
        $compare = ['entity-name', 'entity-phone', 'entity-featured', 'entity-email', 'entity-website', 'entity-address', 'entity-map', 'entity-specialty', 'entity-working-hours', 'entity-avatar', 'entity-photo', 'entity-video', 'entity-tags', 'entity-tariff', 'entity-content', 'entity-mo-open', 'entity-mo-close', 'entity-tu-open', 'entity-tu-close', 'entity-we-open', 'entity-we-close', 'entity-th-open', 'entity-th-close', 'entity-fr-open', 'entity-fr-close', 'entity-sa-open', 'entity-sa-close', 'entity-su-open', 'entity-su-close'];

        foreach ( $structures['titles'] as $k => $v ) {
            if ( !in_array( $k, $compare ) ) { continue; }
            if ( $data[ $id ]['meta'][ $k ] === $data[ $id ]['cached']['meta'][ $k ] ) { continue; }
            $difference[ $k ] = [
                'title' => $v,
                'before' => $data[ $id ]['cached']['meta'][ $k ],
                'after' => $data[ $id ]['meta'][ $k ],
            ];
        }

        return $difference;

    }

    private static function message_content($recipient, $topic, $id = '') {

        $details = self::details();

        if ( !self::$messages[ $recipient ] || !self::$messages[ $recipient ][ $topic ] ) { return; }
        
        $m = self::$messages[ $recipient ][ $topic ];

        $entity = self::get_data( $id )[$id]['title'];

        // translations
        $replacements = function($text) use ($details, $id, $entity) { //++move as a separate method when is demanded by other sending options
            return strtr( $text, [ // ++use this method on mail-template.html too instead of sprintf
                '%expire_days' => '30',
                '%listing_link' => '<a href="'.$details['url'].'/?p='.$id.'">'.$entity.'</a>',
                '%listing_edit_link' => '<a href="'.$details['url'].'/wp-admin/post.php?post='.$id.'&action=edit">'.__( 'Prolong', 'fcpfo--mail' ).'</a>',
                '%tutorial_link' => '<a href="'.$details['url'].'/unternehmenseintrag-verlaengern/">'.__( 'How to renew your listing', 'fcpfo--mail' ).'</a>',
            ]);
        };
        
        $locale = $details[ $recipient . '_locale' ]; // ++make dynamic, according to user's settings
        switch_to_locale( $locale );
        load_textdomain( 'fcpfo--mail', __DIR__ . '/languages/fcpfo--mail-'.$locale.'.mo' );

        $subject = __( $m['subject'] ? $m['subject'] : $m['heading'], 'fcpfo--mail' );
        $subject = $replacements( $subject );
        $heading = __( isset( $m['heading'] ) ? $m['heading'] : $m['subject'], 'fcpfo--mail' );
        $heading = $replacements( $heading );

        $footer = '<a href="'.$details['url'].'">'.$details['domain'].'</a> | <a href="'.$details['url'].'/impressum/">'.__( 'Legal Notice', 'fcpfo--mail' ).'</a> | <a href="'.$details['url'].'/datenschutzerklarung/">'.__( 'Privacy Policy', 'fcpfo--mail' ).'</a>';

        $message = __( $m['content'], 'fcpfo--mail' );
        $message = $replacements( $message );

        if ( $id ) {
            $message  = '
                '.$message.'
                <h2>'.$entity.'</h2>
                <a href="'.$details['url'].'/?p='.$id.'">'.__( 'View the listing', 'fcpfo--mail' ).'</a> | <a href="'.$details['url'].'/wp-admin/post.php?post='.$id.'&action=edit">'.__( 'Edit the listing', 'fcpfo--mail' ).'</a>
            ';
            
            $datalist = self::message_datalist( $id, empty( $m['list'] ) ? null : $m['list'] ); // ++translations
            $message .= $datalist ? "\n".$datalist : '';
        }
        
        $message = wpautop( $message );
        
        restore_previous_locale();
        
        return [
            'subject' => $subject,
            'heading' => $heading,
            'message' => $message,
            'footer' => $footer,
            '_recipient' => $recipient,
            '_topic' => $topic,
        ];
    }
    
    public static function to_accountant($topic, $id = '') { // it is best to run get_data first, if multiple ids

        $message = self::message_content( 'accountant', $topic, $id );
        if ( !$message ) { return; }

        $details = self::details();

        $message['from'] = $details['sending'];
        $message['from_name'] = $details['sending_name'];
        $message['to'] = [ $details['accountant'], $details['admin'] ]; // ++--admin is for testing here for now
        
        if ( $id ) {
            $data = self::get_data( $id )[$id];
            $meta = $data['meta'];

            if ( $meta['billing-email'] ) { $message['reply_to'] = $meta['billing-email']; }
            if ( $meta['billing-name'] ) { $message['reply_to_name'] = $meta['billing-name']; }
            
            $message['preheader'] = sprintf(
                __( 'For %s; From %s, %s.' ),
                $data['title'], $meta['billing-company'], $meta['billing-name']
            );
            
            $message['message'] = '<p>' . $message['preheader'] . '</p>' . $message['message'];
        }
        
        return self::send( $message );
    }
    
    public static function to_client($topic, $id) { // it is best to run get_data first, if multiple ids

        $message = self::message_content( 'client', $topic, $id );
        if ( !$message ) { return; }

        $details = self::details();

        $message['from'] = $details['sending'];
        $message['from_name'] = $details['sending_name'];

        $meta = self::get_data( $id )[ $id ]['meta'];
        if ( !$meta['billing-email'] ) { return; }

        $message['to'] = [ $meta['billing-email'], $details['admin'] ]; // ++--admin is for testing here for now
        $message['to_name'] = $meta['billing-name'] ? $meta['billing-name'] : '';

        $message['reply_to'] = $details['accountant'];

        $message['message'] = self::message_wrap( $message );

        return self::send( $message );
    }
    
    public static function to_user($topic, $id) { // it is best to run get_data first, if multiple ids

        $message = self::message_content( 'user', $topic, $id );
        if ( !$message ) { return; }

        $details = self::details();

        $message['from'] = $details['sending'];
        $message['from_name'] = $details['sending_name'];
        
        // ++use the correct locale from user meta - apply in message_content
        // ++add locale to the message body too
        
        $author = self::get_data( $id )[ $id ]['author'];

        $message['to'] = [ $author[0], $details['admin'] ]; // ++--admin is for testing here for now
        $message['to_name'] = $author[1] ? $author[1] : '';

        $message['reply_to'] = $details['moderator'];

        $message['message'] = self::message_wrap( $message );

        return self::send( $message );
    }
    
    public static function to_moderator($topic, $id = '') {

        if ( current_user_can( 'administrator' ) ) { return; }
    
        $difference = []; // exception to print the data changes - don't send if no changes
        if ( $topic === 'entity_updated' ) {
            $difference = self::message_datalist_moderator_changes( $id );
            if ( empty( $difference ) ) { return; }
        }

        $message = self::message_content( 'moderator', $topic, $id );
        if ( !$message ) { return; }

        $details = self::details();

        $message['from'] = $details['sending'];
        $message['from_name'] = $details['sending_name'];
        $message['to'] = [ $details['moderator'], $details['admin'] ]; // ++--admin is for testing here for now
        $message['to_name'] = $details['moderator_name'];

        if ( !empty( $difference ) ) { // ++move to a separate function?
            $message['message'] .= '<h3>'.__( 'Difference' ).':</h3>';
            $message['message'] .= '<ul>';
            foreach ( $difference as $v ) {
                $message['message'] .= '<li>
                    <strong>'.$v['title'].':</strong><br>
                    '.$v['before'].' &ndash; <em>'.__( 'Before' ).'</em><br>
                    '.$v['after'].' &ndash; <em>'.__( 'After' ).'</em>
                </li>';
            }
            $message['message'] .= '</ul>';
        }

/*
        // ++add the user data to contact back just in case. will be needed later for trusted users
        //if ( $meta['billing-email'] ) { $message['reply_to'] = $meta['billing-email']; } // ++user email
        //if ( $meta['billing-name'] ) { $message['reply_to_name'] = $meta['billing-name']; } // ++user name

//*/

        return self::send( $message );
    }

    public static function to_moderator_custom($message) {

        $details = self::details();

        //++move the translation here?
        $message['heading'] = $message['subject'] = sprintf( __( 'Message from %s', 'fcpfo' ),  $message['name'] );
        $message['footer'] = $details['footer'];

        $message['from'] = $details['sending'];
        $message['from_name'] = $message['name'];
        $message['to'] = [ $details['moderator'], $details['admin'] ]; // ++--admin is for testing here for now
        $message['to_name'] = $details['moderator_name'];
        
        $message['reply_to'] = $message['email'];
        $message['reply_to_name'] = $message['name'];

        $message['message'] = wpautop( esc_html( stripslashes( $message['message'] ) ) );

        return self::send( $message );
    }
    
    public static function send($m) {

        // don't send from local server
        if ( strpos( $_SERVER['HTTP_HOST'], '.' ) === false ) { return; } 

        // don't send from staging
        if ( !empty( $_COOKIE['RAIDBOXES_STAGING'] ) ) { return; }

        if ( !empty( array_diff( ['subject', 'message', 'from', 'to'], array_keys( $m ) ) ) ) { return; }

        static $template = '';
        
        if ( !$template ) {
            $template = file_get_contents( __DIR__ . '/mail-template.html' );
            $template = $template === false ? '<template hidden>%s %s</template><h1>%s</h1> %s <p>%s</p>' : $template;
        }

        $vsprintf = function($a, $arr) {
            $a = str_replace( ['%', '|~~|s'], ['|~~|', '%s'], $a );
            $a = vsprintf( $a, $arr );
            $a = str_replace( '|~~|', '%', $a );
            return $a;
        };

        $email_body = $vsprintf( $template, [
            $m['subject'], // title
            !empty( $m['preheader'] ) ? $m['preheader'] : substr( strip_tags( $m['message'] ), 0, 80 ) . '…', // preview header
            $m['heading'], // h1
            $m['message'], // the content
            $m['footer'] ? $m['footer'] : self::details()['footer'] // footer
        ]);

        if ( is_array( $m['to'] ) && $m['to'][1] ) { list( $m['to'], $m['to2'] ) = $m['to']; }

        if ( !class_exists( '\PHPMailer\PHPMailer\PHPMailer', false ) ) { // not sure it is needed and works
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        }
        if ( !class_exists( '\PHPMailer\PHPMailer\Exception', false ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isHTML( true );
        $mail->CharSet = 'UTF-8';
        $mail->setFrom( $m['from'], $m['from_name'] );
        $mail->addAddress( $m['to'], $m['to_name'] );
        if ( !empty( $m['to2'] ) ) { $mail->AddBCC( $m['to2'] ); }
        if ( !empty( $m['reply_to'] ) ) { $mail->addReplyTo( $m['reply_to'], $m['reply_to_name'] ); }
        $mail->Subject = $m['subject'];
        //$mail->msgHTML( $email_body );
        $mail->Body = $email_body;
        //$mail->AltBody = '';
        $mail->AddEmbeddedImage( __DIR__ . '/attachments/klinikerfahrungen-logo.png', 'klinikerfahrungen-logo');
        // $mail->addAttachment( __DIR__ . '/attachments/Fünf Tipps.pdf' );

        // a small debug
        if ( self::$details['debug'] ) {
            return fct1_log( [ self::$details, $m ], __DIR__ );
        }
        
        // SMTP
        $details = self::details();
        if ( !$details['issmtp'] || empty( $details['smtp'] ) ) { return $mail->send(); }

        if ( !class_exists( '\PHPMailer\PHPMailer\SMTP', false ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        }

        $mail->isSMTP();
        foreach ( $details['smtp'] as $k => $v ) {
            $mail->{ $k } = $v;
        }

        if ( isset( $details['smtp']['SMTPDebug'] ) && $details['smtp']['SMTPDebug'] ) {
            $mail->send();
            exit;
        }

        return $mail->send();

    }

    private static function WPMailSMTP() {

        $smtp = get_option( 'wp_mail_smtp' );

        if ( $smtp['mail']['mailer'] === 'smtp' ) {

            $details['smtp']['Host'] = $smtp['smtp']['host'];
            $details['smtp']['Port'] = $smtp['smtp']['port'];
            $details['smtp']['SMTPSecure'] = $smtp['smtp']['encryption'];

            if ( $smtp['smtp']['auth'] && class_exists( '\WPMailSMTP\Helpers\Crypto' ) ) {
                $details['smtp']['SMTPAuth'] = $smtp['smtp']['auth'];
                $details['smtp']['Username'] = $smtp['smtp']['user'];

                $decrypt =  new \WPMailSMTP\Helpers\Crypto;
                $details['smtp']['Password'] = $decrypt::decrypt( $smtp['smtp']['pass'] );
            }

            if ( $smtp['mail']['from_email_force'] ) {
                $details['sending'] = $smtp['mail']['from_email'];
            }

            if ( $smtp['mail']['from_name_force'] ) {
                $details['sending_name'] = $smtp['mail']['from_name'];
            }

            if ( self::$details['smtpdebug'] ) {
                $details['smtp']['SMTPDebug'] = true;
            }

            return $details;
        }

    }
    
    private static function message_wrap($message) {

        // before
        $message['message'] = '<p>' .
            __( 'Hey', 'fcpfo--mail' ) .
            ( $message['to_name'] ? ' ' . $message['to_name'] : '' ) . ',</p>' .
            $message['message'];

        // after
        $message['message'] .= '<p>' . 
            sprintf( __( 'If you need assistence, please contact our team at: %s.', 'fcpfo--mail' ), $message['from'] ) . 
            '</p>' .
            '<p><em>' .
            sprintf( __( "Greetings from Berlin,\nTeam %s", 'fcpfo--mail' ), self::details()['domain'] ) .
            '</em></p>';

        return $message['message'];
    }

}
