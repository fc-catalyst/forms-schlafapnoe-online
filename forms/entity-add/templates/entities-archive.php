<?php

namespace fcpf\eaa;

include_once ( __DIR__ . '/functions.php' );
use fcpf\eat as eat;

get_header();

// HEADER
$the_query = new \WP_Query( [
    'post_type'        => 'fct-section',
    'name'        => 'entities-hero'
]);

if ( $the_query->have_posts() ) {
    ?><style>body::before{content:none}</style><?php
    while ( $the_query->have_posts() ) {
        $the_query->the_post();
?>		
        <div class="entry-content">
            <?php the_content() ?>
        </div>
<?php
    }
    wp_reset_postdata();
}


// SEARCH QUERY
$args = [
    'post_type'        => ['clinic', 'doctor'],
    'orderby'          => 'date',
    'order'            => 'DESC',
    'posts_per_page'   => '12',
    'paged'            => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
    'meta_query'       => archive_filters(),
];
$wp_query = new \WP_Query( $args );


// SEARCH FORM
?>
    <div class="entry-content">
        <div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
        <?php search_stats( '<p style="margin-top:-25px;opacity:0.45">', '.</p>', true ) ?>
        <?php echo do_shortcode('[fcp-form dir="entity-search" notcontent]') ?>
        <div style="height:1px" aria-hidden="true" class="wp-block-spacer"></div>
    </div>

    <style>.entity-about .cr_stars_bar{width:50%;max-width:115px;margin-top:8px;}</style>

<?php

?><div class="wrap-width"><?php

// FOUND RESULTS
if ( $wp_query->have_posts() ) {
    while ( $wp_query->have_posts() ) {
        $wp_query->the_post();

        eat\entity_tile_print();
        
    }
    get_template_part( 'template-parts/pagination' );
} else {

// NOT FOUND ENTRIES
    search_stats( '<noscript id="nothing-found"><h2>', '</h2></noscript>' );
}

?></div><?php

// LOAD MORE IF NOT ENOUGH FOUND in 100 km radius and by common search results
if ( $args['meta_query'] && $wp_query->post_count < 7 ) {
    ?>
    <script type="text/javascript">
    fcLoadScriptVariable( '/wp-content/plugins/fcp-forms/forms/entity-add/templates/assets/load-in-radius.js', 'fcFoundInRadius', () => {
        fcLoadScriptVariable( '/wp-content/plugins/fcp-forms/forms/entity-add/templates/assets/load-in-search.js' );
    }, ['jQuery'] );
    </script>
    <?php
}

wp_reset_query();

?>
<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
<?php

get_footer();



// FUNCTIONS

function archive_filters() {
    global $wpdb;

    $query_meta = [];

    if ( !empty( $_GET['place'] ) ) {
        $val = $wpdb->_real_escape( htmlspecialchars( urldecode( $_GET['place'] ) ) );

        $query_meta[] = [
            'relation' => 'OR',
            [
                'key' => 'entity-region',
                'value' => $val
            ],
            [
                'key' => 'entity-geo-city',
                'value' => $val
            ],
            [
                'key' => 'entity-geo-postcode',
                'value' => $val
            ]
        ];
    }

    if ( !empty( $_GET['specialty'] ) ) {
        $val = $wpdb->_real_escape( htmlspecialchars( urldecode( $_GET['specialty'] ) ) );

        $query_meta[] = [ [
                'key' => 'entity-specialty',
                'value' => $val
            ]
        ];
    }


    if ( count( $query_meta ) > 1 ) {
        $query_meta['relation'] = 'AND';
        return $query_meta;
    }
    
    return !empty( $query_meta[0] ) ? $query_meta[0] : null;
}

function search_stats($before = '', $after = '', $hide_empty = false ) {
    if ( empty( $_GET['specialty'] ) && empty( $_GET['place'] ) ) { return; }
    
    global $wp_query;
    if ( $wp_query->have_posts() ) {
        if ( $wp_query->found_posts === 1 ) {
            $count = __( '1 result', 'fcpfo-ea' );
        } else {
            $count = sprintf( __( '%s results', 'fcpfo-ea' ), $wp_query->found_posts );
        }
    } else {
        if ( $hide_empty ) { return; }
        $count = __( 'Nothing', 'fcpfo-ea' );
    }
    
    echo $before .
        sprintf( __( '%s found', 'fcpfo-ea' ), $count ) . 
        ( $_GET['specialty'] ? ' für <strong>' . $_GET['specialty'] . '</strong>' : '' ) .
        ( $_GET['place'] ? ' in <strong>' . $_GET['place'] . '</strong>' : '' ) .
        $after;
    
}
