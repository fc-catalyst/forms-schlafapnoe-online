<?php

namespace fcpf\eat;

function print_video() {
    $url = fct1_meta( 'entity-video' );

    if ( !$url ) { return; }

    // direct video
    $video_formats = ['mp4', 'webm', 'wmv', 'mov', 'avi', 'ogg'];
    $format = strtolower( substr( $url, strrpos( $url, '.' ) + 1 ) );
    if ( in_array( $format , $video_formats ) ) {

        ?>
        <div class="entity-video fct1-video" data-source="direct" data-src="<?php echo $url ?>" data-type="video/<?php echo $format ?>" data-error="<?php _e( 'Your browser does not support HTML video.', 'fcpfo-ea' ) ?>"></div>
        <?php

        return;
    }
    
    // youtube
	if ( preg_match( '/^https?\:\/\/(?:www\.)?youtu(?:.)+[=\/]{1}([\w_\-]{11})(?:[^\w_\-].+)*$/i', $url, $match ) ) {
        ?>
        <div class="entity-video fct1-video" data-source="youtube" data-src="https://www.youtube.com/embed/<?php echo $match[1] ?>?feature=oembed&autoplay=0"><?php echo do_shortcode( '[borlabs-cookie id="youtube" type="content-blocker"] [/borlabs-cookie]' ) ?></div>
        <?php
    }

}

function print_gmap() {
    
    // address (required)
    $addr = fct1_meta( 'entity-address' );
    ?>
    <?php echo $addr ? '<meta itemprop="address" content="'.$addr.'">' : '' ?>
    <?php
    
    // contact point (phone is required)
    ?>
    <div itemprop="contactPoint" itemscope itemtype="https://schema.org/ContactPoint">
        <meta itemprop="contactType" content="customer service">
        <meta itemprop="telephone" content="<?php echo fct1_meta( 'entity-phone' ) ?>">
    </div>
    <?php
    
    // google maps print (required, but has legacy)
    list( $lat, $long, $zoom ) = [
        fct1_meta( 'entity-geo-lat' ),
        fct1_meta( 'entity-geo-long' ),
        fct1_meta( 'entity-zoom' )
    ];

    if ( !$lat ) return;
   
    ?>
    <div class="entity-map fct1-gmap-view" itemprop="geo" itemscope itemtype="https://schema.org/GeoCoordinates"
        <?php echo $addr ? 'data-addr="'.$addr.'"' : '' ?>
        <?php echo $lat ? 'data-lat="'.$lat.'"' : '' ?>
        <?php echo $long ? 'data-lng="'.$long.'"' : '' ?>
        <?php echo $zoom ? 'data-zoom="'.$zoom.'"' : '' ?>
        <?php echo 'data-title="'.get_the_title().'"' ?>
    >
        <?php echo $lat ? '<meta itemprop="latitude" content="'.$lat.'">' : '' ?>
        <?php echo $long ? '<meta itemprop="longitude" content="'.$long.'">' : '' ?>
        <?php echo do_shortcode( '[borlabs-cookie id="googlemaps" type="content-blocker"] [/borlabs-cookie]' ) ?>
    </div>
    <?php
}

function print_contact_buttons($sidebar = false) {
    ?>
    <div class="fct1-group<?php echo $sidebar ? ' sidebar' : '' ?>">
    <?php
    print_contact_button( 'entity-phone', fct1_meta( 'entity-phone' ), 'telephone' );
    print_contact_button( 'entity-email', __( 'E-mail', 'fcpfo-ea' ) );
    print_contact_button( 'entity-website', __( 'Website', 'fcpfo-ea' ), 'url' );
    ?>
    </div>
    <?php
}

function print_contact_button($meta_name, $name, $itemprop = '') {
    $meta_value = fct1_meta( $meta_name );
    if ( !$meta_value ) { return; }

    if ( strpos( $meta_name, 'phone' ) !== false ) { $prefix = 'tel:'; $img = 'phone'; }
    if ( strpos( $meta_name, 'mail' ) !== false ) { $prefix = 'mailto:'; $img = 'mail'; }
    if ( strpos( $meta_name, 'website' ) !== false ) {
        $img = 'website';
        $attrs = ' target="_blank"';

        $tariff_running = fcp_tariff_get()->running;
        switch ( $tariff_running ) {
            case 'standardeintrag':
                $attrs .= ' rel="nofollow noopener noreferrer"';
                break;
            case 'premiumeintrag':
                $attrs .= ' rel="noopener"';
                break;
            default:
                $attrs .= ' rel="nofollow noopener noreferrer"';
        }
    }
//++ fix the imgs url again
    ?>
        <div class="fct1-tile-one">
            <a href="<?php echo isset( $prefix ) ? $prefix : '' ?><?php echo $meta_value ?>"<?php echo isset( $attrs ) ? $attrs : '' ?>></a>
            <img width="100" height="80" src="/wp-content/plugins/fcp-forms/forms/entity-add/templates/imgs/<?php echo $img ?>.svg" class="attachment-full size-full" alt="" loading="lazy">
            <p<?php echo $itemprop ? ' itemprop="'.$itemprop.'" content="'.$meta_value.'" ' : '' ?>><?php echo $name ?></p>
        </div>
    <?php
}

function entity_print_workhours($toggle_in = false) {

    $fields = [
        'entity-mo' => 'Monday', // -open, -close, translation goes lower
        'entity-tu' => 'Tuesday',
        'entity-we' => 'Wednesday',
        'entity-th' => 'Thursday',
        'entity-fr' => 'Friday',
        'entity-sa' => 'Saturday',
        'entity-su' => 'Sunday'
    ];

    $values = [];
    $schema = []; // ++use lunch breaks later
    foreach ( $fields as $k => $v ) {
        $open = fct1_meta( $k . '-open' );
        $close = fct1_meta( $k . '-close' );

        if ( !empty( $open ) ) {
            foreach ( $open as $l => $w ) {
                if ( !$close[ $l ] ) {
                    continue;
                }
                $values[ $k ][] = $open[ $l ] . ' - ' . $close[ $l ]; // format
                $schema[ $k ]['open'] = isset( $schema[ $k ]['open'] ) ? $schema[ $k ]['open'] : $open[ $l ];
                $schema[ $k ]['close'] = $close[ $l ];
            }
            if ( !empty( $values[ $k ] ) ) { continue; }
        }
        
        $values[ $k ][] = '<small>' . \__( 'Closed', 'fcpfo-ea' ) . '</small>';

    }
    
    if ( empty( $schema ) ) { return; }
    
    ?>
<div class="entity-workhours">
    <a href="#" class="fct1-open-next<?php echo $toggle_in ? ' active' : '' ?>"><?php _e( 'Working hours', 'fcpfo-ea' ) ?></a>
    <dl>
    <?php
    
    foreach ( $values as $k => $v ) {
        ?>
        <dt>
            <?php echo __( $fields[ $k ] ) ?>
        </dt>
        <dd>
            <?php echo implode( '<br/>', $v ) ?>
        </dd>

        <?php if ( empty( $schema[ $k ] ) ) { continue; } ?>
        <meta itemprop="openingHours" content="<?php
            echo substr( $fields[ $k ], 0, 2 ) . ' ' .
                 $schema[ $k ]['open'] . '-' .
                 $schema[ $k ]['close'];
        ?>">
        <?php
    }
    
    ?>
    </dl>
</div>
    <?php
}

function entity_print_gallery() {

    $gallery = fct1_meta( 'gallery-images' );
    if ( empty( $gallery ) ) { return; }

?>
    <div id="entity-gallery">
        <?php foreach ( $gallery as $v ) { ?>
            <figure class="wp-block-image">
                <a href="<?php echo fct1_image_src( 'entity/' . get_the_ID() . '/gallery/' . $v )[0] ?>">
                    <?php fct1_image_print( 'entity/' . get_the_ID() . '/gallery/' . $v, [554,554] ) ?>
                </a>
            </figure>
        <?php } ?>
    </div>
<?php
}

function entity_content_filter($text) {
    if ( !$text ) { return ''; }
    return fcp_tariff_filter_text( apply_filters( 'the_content', $text ) );
    //return apply_filters( 'the_content', $text );
}

function entity_print_tags() {
    echo fct1_meta( 'entity-tags', '<h2>'.__( 'Our range of treatments', 'fcpfo-ea' ).'</h2><p>', '</p>' );
    // Unsere angebotenen Behandlungen
}

function entity_photo_print() {
    //<meta itemprop="image" content="<?php echo $back_img[0]
    $img = fct1_meta( 'entity-photo' );
    if ( !$img || !$img[0] ) { return; }
    $img = fct1_image_src( 'entity/' . get_the_ID() . '/' . $img[0], [800,500] );
    ?>
    <div class="entity-photo"><img src="<?php echo $img[0] ?>" alt="<?php the_title(); echo ' ' . __( 'Photo', 'fcpfo-ea' ) ?>" width="<?php echo $img[1] ?>" height="<?php echo $img[2] ?>" loading="lazy" itemprop="<?php echo get_post_type() === 'doctor' ? 'image' : 'photo' ?>"/></div>
    <?php
}

function entity_tile_print($footer = '') {
?>
    <article class="post-<?php the_ID() ?> <?php echo get_post_type() ?> type-<?php echo get_post_type() ?> status-<?php echo get_post_status() ?> entry" itemscope="" itemtype="https://schema.org/CreativeWork">

        <a class="entry-link-cover" href="<?php the_permalink(); ?>" title="<?php the_title() ?>"></a>

        <header class="entry-header">
        <?php
            $photo = fct1_meta( 'entity-photo', '', '', true )[0];
            $backg = fct1_meta( 'entity-background', '', '', true )[0];
            if ( $photo || $backg ) {
        ?>
            <div class="entry-photo<?php echo !$photo ? ' entry-background' : '' ?>">
                <?php
                    fct1_image_print(
                        'entity/' . get_the_ID() . '/' . ( $photo ?: $backg ),
                        [454, 210],
                        ['center', 'top'],
                        get_the_title()
                    )
                ?>
            </div>
        <?php } ?>
            <h2 class="entry-title" itemprop="headline">
                <a href="<?php the_permalink() ?>"><?php the_title() ?></a>
            </h2>
            <div class="entry-badges">
                <div class="entry-verified" title="<?php _e( 'Verified', 'fcpfo-ea' ) ?>"></div>
                <?php if ( fct1_meta( 'entity-featured' ) ) { ?>
                <div class="entry-featured" title="<?php _e( 'Featured', 'fcpfo-ea' ) ?>"></div>
                <?php } ?>
            </div>
        </header>
        <div class="entry-details">
            <?php if ( $ava = fct1_meta( 'entity-avatar', '', '', true )[0] ) { ?>
            <div class="entity-avatar">
                <?php fct1_image_print( 'entity/' . get_the_ID() . '/' . $ava, [74,74], 0, get_the_title() . ' ' . __( 'Icon', 'fcpfo-ea' ) ) ?>
            </div>
            <?php } ?>
            <div class="entity-about">
                <p>
                    <?php echo fct1_meta( 'entity-specialty' ); echo fct1_meta( 'entity-geo-city', ' in ' ) ?>
                </p>
                <?php if ( method_exists( '\FCP_Comment_Rate', 'stars_total_print' ) ) { ?>
                    <?php \FCP_Comment_Rate::stars_total_print() ?>
                <?php } ?>
            </div>
        </div>
        <?php if ( !empty( $footer ) ) { ?>
        <footer>
            <?php echo $footer ?>
        </footer>
        <?php } ?>
    </article>
<?php
}
