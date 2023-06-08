// basically, it is a copy of scripts.js, but without popups and with scripts loaded initially

fcLoadScriptVariable(
    'https://maps.googleapis.com/maps/api/js?key='+fcGmapKey+'&libraries=places&&language=de-DE',
    'google'
);

fcLoadScriptVariable(
    '/wp-content/themes/fct1/assets/smarts/gmap-view.js',
    'fcAddGmapView'
);

fcLoadScriptVariable(
    '/wp-content/themes/fct1/assets/smarts/gmap-pick.js',
    'fcAddGmapPick'
);

fcLoadScriptVariable(
    '',
    'google',
    function() {

        if ( !~location.hostname.indexOf('.') ) { return }

        const $ = jQuery;

        // gmap-------------------------------
        const countries = ['de', 'at', 'ch']; // Germany, Austria, Switzerland
        const $gmap_holder = $( '.fct1-gmap-pick' );
        $gmap_holder.css( 'min-height', '312px' );

        function getLatLngZoom() {
            const default_props = { // ++add default country pick by language or IP
                lat: 51.1243545,
                lng: 10.18524,
                zoom: 6
            },
            props = {
                lat: Number( $( '#entity-geo-lat_entity-add' ).val() ),
                lng: Number( $( '#entity-geo-long_entity-add' ).val() ),
                zoom: Number( $( '#entity-geo-zoom_entity-add' ).val() ) || default_props.zoom
            };
            
            return props.lat && props.lng ? props : default_props;
        }

        // gmap print
        if ( !$gmap_holder.length ) { return }

        const gmap = fcAddGmapView( $gmap_holder, false, getLatLngZoom() ),
              marker = fcAddGmapPick( gmap, $gmap_holder[0] );

        // apply new values after moving the marker
        $gmap_holder[0].addEventListener( 'map_changed', function(e) {
            setTimeout( function() { // wait till new values are applied to the map
                $( '#entity-geo-lat_entity-add' ).val( e.detail.marker.getPosition().lat() );
                $( '#entity-geo-long_entity-add' ).val( e.detail.marker.getPosition().lng() );
                $( '#entity-geo-zoom_entity-add' ).val( e.detail.gmap.getZoom() );
            });
        });

        
        // autocomplete-------------------------------
        const $input = $( '#entity-address_entity-add' );
        if ( !$input.length ) { return }


        let is_correct = false; // make sure, the visitor used the autocomplete, so the hidden fields are filled correctly

        // autocomplete with an advisor
        const ac = new google.maps.places.Autocomplete(
            $input[0],
            {
                componentRestrictions: { country: countries }, // Germany, Austria, Switzerland
                fields: ['address_components', 'formatted_address', 'geometry'], // ++'place_id' to load rating someday
                types: ['address']
            }
        );

        ac.addListener( 'place_changed', function() { // the correct way of filling the address field
            fillInValues( ac.getPlace() );
        });

        $input.on( 'input', function() { // any manual change must be verified / modified by the api
            is_correct = false;
        });


        let freeze = false; // freezes the value from changes by geocoder, if the field is in focus
        $input.on( 'blur', function() { // verify / modify / format the value by the api
            freeze = false;
            setTimeout( function() { // just a measure of economy, as `blur` fires before `place_changed`
            
                if ( is_correct ) { return }

                autosuggest( $input.val(),
                    function( place ) {
                        if ( is_correct || freeze ) { return }
                        fillInValues( place );
                    },
                    fillInValues // just passes empty value
                );

            }, 200 );

        });

        const $form = $input.parents( 'form' );
        $form.on( 'submit', function(e) { // don't submit the form before the address is modified

            if ( is_correct ) { return } // && $input.not( ':focus' ) OR && !freeze

            e.preventDefault();
            e.originalEvent.submitter.setAttribute( 'type', 'hidden' ); // the submit button value also gotta be submitted

            autosuggest( $input.val(),
                function( place ) {
                    fillInValues( place );
                    submit();
                },
                submit
            );

            function submit() {
                is_correct = true;
                $form.submit();
            }

        });

        if ( $input.is( ':focus' ) ) {
            freeze = true;
        }
        $input.on( 'focus', function() {
            freeze = true;
        });


        function fillInValues(place) {

            const values = {
                'region': '',
                'geo-city': '',
                'geo-postcode': '',
                'geo-lat': '',
                'geo-long': '',
                'geo-street_number' : ''
            },
                prefix = 'entity-',
                postfix = '_entity-add';

            if ( !place || !place.geometry ) { apply_values(); return; } // autocomplete couldn't suggest anything proper
            
            is_correct = true;

            let postcode = '';
            for ( const component of place.address_components ) {
                const componentType = component.types[0];
                switch (componentType) {
                    case 'postal_code': { // postcode
                        postcode = `${component.long_name}${postcode}`;
                        break;
                    }
                    case "postal_code_suffix": { // postcode
                        postcode = `${postcode}-${component.long_name}`;
                        break;
                    }
                    case "locality": { // city
                        values['geo-city'] = component.long_name;
                        break;
                    }
                    case "administrative_area_level_1": { // region
                        values['region'] = component.short_name;
                        break;
                    }
                    case "street_number": { // demanded attribute for the address to be correct, not saving
                        values['geo-street_number'] = component.short_name || component.long_name;
                        break;
                    }
                }
            }

            values['geo-postcode'] = postcode;
            values['geo-lat'] = place.geometry.location.lat();
            values['geo-long'] = place.geometry.location.lng();

            const geo_zoom = $( '#'+prefix+'geo-zoom'+postfix ).val();
            values['geo-zoom'] = geo_zoom ? geo_zoom : '17';

            apply_values();

            gmap_move();

            // format the main address field
            $input.val( place.formatted_address );

            function apply_values() {
                for ( let i in values ) {
                    $( '#'+prefix+i+postfix ).val( values[i] );
                }
            }
        }


        $input.keydown( function (e) { // don't submit the form if autocomplete is open
            if ( e.key === 'Enter' && $( '.pac-container:visible' ).length ) {
                e.preventDefault();
            }
        });
        
        function gmap_move() {
            const props = getLatLngZoom();
            gmap.panTo( props );
            gmap.setZoom( 17 );
            marker.setPosition( props );
        }
        
        function autosuggest(address, success_func, fail_func) {

            if ( !address ) { return }
            if ( !success_func || typeof success_func !== 'function' ) { success_func = (a) => {}; }
            if ( !fail_func || typeof fail_func !== 'function' ) { fail_func = () => {}; }

            const geocoder = new google.maps.Geocoder();
            if ( !geocoder || !geocoder.geocode ) { fail_func(); return }

            let i = 0;
            iterate();

            function iterate() {
                if ( i >= countries.length ) { fail_func(); return }
                geocoder.geocode(
                    {
                        componentRestrictions: { country: countries[i] },
                        address: address
                        //placeId: placeId
                    },
                    function(places, status) {
                        if ( status !== 'OK' || !places[0] || !places[0].geometry ) {
                            fail_func();
                            return;
                        }
                        if ( !~places[0].formatted_address.indexOf( ',' ) ) { // the address is only the country name
                            i++;
                            iterate();
                            return;
                        }
                        success_func( places[0] );
                    }
                );                
            }
        }
    },
    ['jQuery', 'google', 'fcAddGmapView', 'fcAddGmapPick']
);

// lunch breaks
fcLoadScriptVariable(
    '',
    'jQuery',
    function() {
        const $ = jQuery,
        $lunch = $( '<button type="button" style="float:right;margin:4px 0 0 12px">Wir haben Mittagspausen</button>' );
        $lunch.click( function() {
            const $copy = $( '#entity-working-hours input[type=text] ~ input[type=text]' ) // used to be +
            if ( $copy.length ) {
                $copy.each( function() {
                    const $self = $( this );
                    if ( !!~$self.attr( 'id' ).indexOf( 'open' ) ) {
                        $self.remove();
                    } else {
                        $self.prevAll( 'input[type=text]:first' ).remove();
                    }
                    // $( this ).remove(); // this is normally enough
                });
                return;
            }
            $( '#entity-working-hours input[type=text]' ).each( function(e) {
                const $self = $( this );
                if ( !!~$self.attr( 'id' ).indexOf( 'open' ) ) {
                    $self.clone().insertAfter( $self ).val( '' );
                } else {
                    $self.clone().insertAfter( $self ); // this is normally enough
                    $self.val( '' );
                }
            });
        });
        $( '#entity-working-hours h3' ).append( $lunch );
    }
);

// change the max words limit label
fcLoadScriptVariable(
    '',
    'tinymce',
    async function() {
        const $ = jQuery;
        
        // change the words higher limit after the tariff
        const tariffs = {
            'kostenloser_eintrag' : 450,
            'premiumeintrag' : 850
        };

        let limit = tariffs[ $( '#entity-tariff_entity-tariff' ).val() ];
        $( '#entity-tariff_entity-tariff' ).on( 'change', function() {
            limit = tariffs[ $( this ).val() ];
            $( '.entity-content-words-limit' ).text( limit );
        });
        $( '.entity-content-words-limit' ).text( limit );

        // change the left words counter number
        const $words_left = $( '.entity-content-words-count' );
        let editor = tinymce.get( 'entity-content_entity-add' );
        await new Promise( resolve => { // editor.on loads asyncroniously, so waiting for it
            const check = () => {
                if ( editor !== null && editor.on ) { resolve(); return }
                editor = tinymce.get( 'entity-content_entity-add' );
                setTimeout( check, 500 );
            };
            check();
        });

        const words_left_count = () => {
            const text = editor.getContent( { format : 'text' } );
            const words_count = text.replace( /[\.\,\;\?\!\s_]+/g, ' ' ).trim().split( ' ' ).length;
            const words_left = limit - words_count;
            $words_left.text( words_left );
            if ( words_left < 0 ) {
                $words_left.addClass( 'fcp-form-warning' );
            } else {
                $words_left.removeClass( 'fcp-form-warning' );
            }
        };
        let timer = setTimeout( () => {} );
        const words_left_trigger = () => {
            clearTimeout( timer );
            timer = setTimeout( words_left_count, 1000 );
        };

        editor.on( 'KeyUp', words_left_trigger );
        editor.on( 'Change', words_left_trigger );
        $( '#entity-tariff_entity-tariff' ).on( 'change', function() {
            words_left_count();
        });
        if ( $( '#entity-content_entity-add' ).val() ) {
            words_left_trigger();
        }
    },
    ['jQuery']
);