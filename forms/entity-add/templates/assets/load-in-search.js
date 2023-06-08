(()=>{

    const $ = jQuery,
    _ = new URLSearchParams( window.location.search ),
    [ plc, spc ] = [ _.get('place'), _.get('specialty') ],
    $holder = $( '#main-content .wrap-width' );

    if ( plc === null && spc === null ) { return }

    const query = ( ( plc || '' ) + ' ' + ( spc || '' ) ).trim();

    // get the already printed ids
    const pids = []; // ++ !!works fine only on the first page
    $holder.find( 'article' ).each( function() {
        const cls = $( this ).attr( 'class' );
        if ( !~cls.indexOf( 'post-' ) ) { return true }
        pids.push( cls.replace( /^.*post\-(\d+).*$/, "$1" ) );
    });

    $.get( '/wp-json/fcp-forms/v1/entities_search/' + encodeURI( query ) + ( pids[0] ? '/'+pids.join(',') : '' ), function( data ) {
        $holder.append( '<div id="found-by-query"></div>' );

        const shadow = $( '#found-by-query' )[0];
        shadow.attachShadow( { mode: 'open' } );
        shadow.shadowRoot.innerHTML  = '<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>';
        shadow.shadowRoot.innerHTML += data.content;

        $.get( '/wp-content/themes/fct1/assets/styles/first-screen/search.css', function( data ) {
            shadow.shadowRoot.innerHTML += `<style type="text/css">${data}</style>`;
        });

    }).fail( () => {
        $( '#nothing-found' ).replaceWith( $( '#nothing-found' ).html() );
    });

})();