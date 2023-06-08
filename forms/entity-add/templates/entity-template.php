<?php

include_once ( __DIR__ . '/functions.php' );

$imgs_dir = str_replace( ABSPATH, get_site_url() . '/', dirname( __DIR__ ) . '/templates/images/' );

get_header();


if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();

        $doctor = get_post_type() === 'doctor';
        
        $place = fct1_meta( 'entity-geo-city' ) ? fct1_meta( 'entity-geo-city' ) : fct1_meta( 'entity-region' );
        
        $link_to_search = '/kliniken/?';
        foreach ( [ 'place' => $place, 'specialty' => fct1_meta( 'entity-specialty' ) ] as $k => $v ) {
            if ( !$v ) { continue; }
            $link_to_search .= $k . '=' . urlencode( $v ) . '&';
        }
        $link_to_search = rtrim( $link_to_search, '&' );
?>

<article class="post-<?php the_ID() ?> <?php echo get_post_type() ?> type-<?php echo get_post_type() ?> status-<?php echo get_post_status() ?> entry" itemscope itemtype="https://schema.org/<?php echo $doctor ? 'MedicalOrganization' : 'MedicalClinic' ?>">
    <div class="post-content">
        <div class="entry-content">

<!-- gutenberg copy start -->

<header class="wp-block-columns alignwide are-vertically-aligned-center fct1-hero">

    <div class="wp-block-column is-vertically-aligned-center" style="flex-basis:66.66%">

        <div class="entry-badges">
            <div class="entry-verified" title="<?php _e( 'Verified', 'fcpfo-ea' ) ?>"></div>
            <?php if ( fct1_meta( 'entity-featured' ) ) { ?>
            <div class="entry-featured" title="<?php _e( 'Featured', 'fcpfo-ea' ) ?>"></div>
            <?php } ?>
        </div>

        <h1 itemprop="name"><?php the_title() ?></h1>

        <div class="entry-about"><a href="<?php echo $link_to_search ?>"><?php
                echo fct1_meta( 'entity-specialty', '<span itemprop="medicalSpecialty">', '</span>' );
                echo $place ? ' <span>in '.$place.' </span>' : '';
        ?></a></div>
        
        <?php if ( method_exists( '\FCP_Comment_Rate', 'stars_n_rating_print' ) ) { ?>
        <div class="entry-rating">
            <?php \FCP_Comment_Rate::stars_n_rating_print() ?>
            <style>
            .entry-rating .cr_stars_bar::before {
                background-color:#ffffff33;
            }
            .entry-rating .cr_stars_bar > div {
                background-color:#fff;
            }
            .entry-rating .cr_stars_bar + span {
                margin-left:12px;
                font-size:26px;
                color:#f5b309;
            }
            </style>
        </div>
        <?php } ?>

        <?php if ( comments_open() || get_comments_number() ) { ?>

        <div class="wp-block-buttons entry-rate">
            <div class="wp-block-button is-style-outline">
                <a class="wp-block-button__link has-white-color has-text-color" href="#comments">
                    <?php _e( get_comments_number() ? 'Reviews' : 'Review and Rate', 'fcpcr' ) ?>
                </a>
            </div>
        </div>
        <style>
        .entry-rate {
            margin-top:30px;
        }
        .entry-rate .wp-block-button__link {
            padding-top:0.3em;
            padding-bottom:0.3em;
            text-transform:uppercase;
            font-size:16px;
            font-weight:700;
        }
        .entry-rate .wp-block-button__link::after {
            content:'\000BB';
            display:inline-block;
            margin-left:10px;
            font-weight:300;
            transform:rotate( 90deg ) scale( 1.3 );
        }
        </style>
        <?php } ?>
        
        <?php if (function_exists('yoast_breadcrumb')){yoast_breadcrumb('<div class="yoast-breadcrumbs">','</div>');} ?>
        <?php if (function_exists('rank_math_the_breadcrumbs')){rank_math_the_breadcrumbs();} ?>

    </div>

    <div class="wp-block-column is-vertically-aligned-center" style="flex-basis:33.33%">
        <?php if ( $logo = fct1_meta( 'entity-avatar', '', '', true )[0] ) { ?>
        <div class="entry-avatar">
            <?php fct1_image_print( 'entity/' . get_the_ID() . '/' . $logo, [600,600], 0, get_the_title() . ' ' . __( 'Logo', 'fcpfo-ea' ), $doctor ? 'photo' : 'logo' ) ?>
        </div>
        <?php } ?>
    </div>

    <?php if ( $background = fct1_meta( 'entity-background', '', '', true )[0] ) { ?>
    <div class="entry-background">
        <?php fct1_image_print( 'entity/' . get_the_ID() . '/' . $background, [1400, 600], 1, '', 'image' ) ?>
    </div>
    <?php } ?>

</header>


<div style="height:35px" aria-hidden="true" class="wp-block-spacer"></div>
<?php
if ( get_post_status() !== 'publish' && get_the_author_meta( 'ID' ) === get_current_user_id() ) {
?>
<div class="notice"><?php
    if ( fcp_tariff_get()->paid ) {
        _e( 'Thank you for adding the entry. We will bill you shortly. Your entry will become visible to other visitors as soon as the bill is payed, and the content passes the moderation.', 'fcpfo-ea' );
    } else {
        _e( 'Thank you for adding the entry. It will become visible to other visitors as soon as passes the moderation.', 'fcpfo-ea' );
    }
?></div>
<?php
}
?>

<?php

    $template = [];

    $template[] = 'full';
    
    $countcont = strlen( strip_tags( fct1_meta( 'entity-content' ) ) );
    if ( $countcont < 800 && !fct1_meta( 'entity-tags' ) || $countcont < 400 && !fct1_meta( 'entity-photo' ) ) {
        $template[] = 'nocontent';
    }
    
    if ( empty( fct1_meta( 'gallery-images' ) ) ) {
        $template[] = 'nogallery';
    }
    if ( !fct1_meta( 'entity-video' ) ) {
        $template[] = 'novideo';
    }
    
    if ( !empty( $template[1] ) ) {
        unset( $template[0] );
    }

    if ( current_user_can( 'administrator' ) ) {
        echo '<p>Template: <strong>'.implode( '-', $template ).'</strong></p>';
    }

    include_once ( __DIR__ . '/layouts/' . implode( '-', $template ) . '.php' );

?>

<!-- gutenberg copy end -->

        </div>
    </div>
</article>

<script>
/* vertical gallery crop & add scrolling */
fcLoadScriptVariable(
    '/wp-content/plugins/fcp-forms/forms/entity-add/templates/assets/gallery-vertical.js',
    'fcAddGallery',
    function() { fcAddGallery( '#entity-gallery' ) },
    [],
    true
);
</script>

<?php echo current_user_can( 'edit_post' ) ? edit_post_link() : '' ?>

<?php comments_template() ?>

<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>

<?php

    endwhile;
endif;

get_footer();