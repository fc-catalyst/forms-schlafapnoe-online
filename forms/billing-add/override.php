<?php
/*
Print something else instead of the form
*/

if ( !is_user_logged_in() ) {
    unset( $json->fields );
    $override = '';
    return;
}

// autofill some values if we consider, that previous step was 2
if ( isset( $_GET['step3'] ) ) {

    // pick the newes entity meta
    $wp_query = new WP_Query([
        'author' => wp_get_current_user()->ID,
        'post_type' => ['clinic', 'doctor'],
        'orderby' => 'ID',
        'order'   => 'DESC',
        'post_status' => 'any',
        'posts_per_page' => 1,
    ]);

    // billing-company - get_the_title()
    $autofill = [
        'billing-address' => 'entity-address',
        'billing-region' => 'entity-region',
        'billing-city' => 'entity-geo-city',
        'billing-postcode' => 'entity-geo-postcode',
        'billing-email' => 'entity-email',
    ];

    if ( $wp_query->have_posts() ) {
        while ( $wp_query->have_posts() ) {
            $wp_query->the_post();

            FCP_Forms::json_attr_by_name( $json->fields,
                'billing-company',
                'value',
                get_the_title()
            );

            foreach( $autofill as $k => $v ) {
                FCP_Forms::json_attr_by_name( $json->fields,
                    $k,
                    'value',
                    fct1_meta( $v )
                );
            }

            // add the entity to assign the billing method to
            array_push( $json->fields[0]->fields, (object) [
                'type' => 'hidden',
                'name' => 'entity-id',
                'value' => get_the_ID(),
            ]);

            // just a notice
            array_push( $json->fields[0]->fields, (object) [
                'type' => 'notice',
                'text' => '<p>Nach Bestätigung der Registrierung, erhalten Sie in Kürze eine Rechnung.</p>',
            ]);

            // just a notice
            array_unshift( $json->fields[0]->fields, (object) [
                'type' => 'notice',
                'text' => sprintf(
                    '<p style="margin-bottom:18px"><strong>Hinweis:</strong> Der %s %s wurde erfolgreich gespeichert. Möchten Sie Änderungen vornehmen, folgen Sie dem %s.</p>',
                    get_post_type_object( get_post_type() )->labels->singular_name,
                    get_the_title(),
                    '<a href="' . get_edit_post_link() . '" target="_blank">Link</a>'
                ),
            ]);
            
            break;
        }
        wp_reset_query();
    }

    return;
}


// add the select field to pick the entity
// pick all entities of current user
$wp_query = new WP_Query([
    'author' => wp_get_current_user()->ID,
    'post_type' => ['clinic', 'doctor'],
    'orderby' => 'post_title',
    'order'   => 'ASC',
    'post_status' => 'any',
    'posts_per_page' => -1,
]);

$entities = [];
if ( $wp_query->have_posts() ) {

    while ( $wp_query->have_posts() ) {
        $wp_query->the_post();
        
        $entities[ get_the_ID() ] = get_the_title();
    }
    wp_reset_query();
    
    // add the options
    array_push( $json->fields[0]->fields, (object) [
        'type' => 'select',
        'title' => 'Rechnung einem Arzt oder Klinik zuweisen', // Assign this billing to a Clinic or Doctor
        'placeholder' => 'None',
        'name' => 'entity-id',
        'options' => (object) $entities,
    ]);

}