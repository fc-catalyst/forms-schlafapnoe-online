(()=>{

    let fallback = setTimeout( ()=>{ window.fcFoundInRadius = 'probably' }, 2000 );
    // fallback in case search is meant initially or google is absent
    // triggers loading search results

    const $ = jQuery,
    _ = new URLSearchParams( window.location.search ),
    [ plc, spc ] = [ _.get('place'), _.get('specialty') ],
    $holder = $( '#main-content .wrap-width' );

    if ( plc === null || spc === null ) { return } // no place - no radius, no specialty - all is already printed
    if ( $holder.length === 0 || $holder.find( 'article' ).length > 6 ) { return }


    fcLoadScriptVariable( // it gets the lat-lng by the address part (region, postcode, city) // try using own resources
    'https://maps.googleapis.com/maps/api/js?key='+fcGmapKey+'&libraries=places&language=de-DE',
    'google',
    function() {

        clearTimeout( fallback );
        fallback = setTimeout( ()=>{ window.fcFoundInRadius = 'maybe' }, 1000 );

        if ( !~location.hostname.indexOf('.') ) { return } // localhost

        // get the already printed ids
        const pids = []; // ++ !!works fine only on the first page
        $holder.find( 'article' ).each( function() {
            const cls = $( this ).attr( 'class' );
            if ( !~cls.indexOf( 'post-' ) ) { return true }
            pids.push( cls.replace( /^.*post\-(\d+).*$/, "$1" ) );
        });

        // get the lat lng by address (region, postcode, city) to count the radius from
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode(
            {
                componentRestrictions: { country: 'de' },
                address: plc
            },
            function(places, status) {

                clearTimeout( fallback );
                fallback = setTimeout( ()=>{ window.fcFoundInRadius = 'almost' }, 500 );

                if ( status !== 'OK' || !places[0] ) { return }

                const [ lat, lng ] = [ places[0].geometry.location.lat(), places[0].geometry.location.lng() ];
                if ( !lat || !lng ) { return }

                $.get( '/wp-json/fcp-forms/v1/entities_around/' + [lat,lng,spc].join('/') + (pids[0] ? '/'+pids.join(',') : ''), function( data ) {
                    $holder.append( data.content );
                    clearTimeout( fallback );
                    window.fcFoundInRadius = 'found';
                });

            }
        );
    });
})();