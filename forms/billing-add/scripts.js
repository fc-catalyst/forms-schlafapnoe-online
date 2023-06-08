fcLoadScriptVariable(
    'https://maps.googleapis.com/maps/api/js?key='+fcGmapKey+'&libraries=places&&language=de-DE',
    'google',
    function() {

        if ( !~location.hostname.indexOf('.') ) { return }

        let autocompleteFilled = false; // make sure, the visitor used the autocomplete popup
        const $ = jQuery,
              $autocompleteInput = $( '#billing-address_billing-add' );
        if ( !$autocompleteInput.length ) { return }

        const autocomplete = new google.maps.places.Autocomplete(
            $autocompleteInput[0],
            {
                componentRestrictions: { country: ['de'] },
                fields: ['address_components', 'formatted_address'],
                types: ['address']
            }
        );

        autocomplete.addListener( 'place_changed', function() {
            fillInValues( autocomplete.getPlace() );
        });

        $autocompleteInput.on( 'input', function() { // any manual input must be corrected
            autocompleteFilled = false;
        });

        $autocompleteInput.on( 'blur', function() {
            
            setTimeout( function() { // should wait for autocompete if is - a measure of economy
            
                if ( autocompleteFilled ) { return }
                
                let geocoder = new google.maps.Geocoder();

                geocoder.geocode(
                    {
                        componentRestrictions: { country: 'de' },
                        address: $autocompleteInput.val()
                    },
                    function(results, status) {
                        if ( status !== 'OK' ) { return }
                        if ( autocompleteFilled ) { return }
                        fillInValues( results[0] );
                    }
                );
            }, 1000 );

        });
        
        $autocompleteInput.keydown( function (e) { // fix the enter clicn to not submit the form, but select
            if ( e.key === 'Enter' && $( '.pac-container:visible' ).length ) {
                e.preventDefault();
            }
        });


        function fillInValues(result) {

            autocompleteFilled = true;

            let postcode = '';

            for ( const component of result.address_components ) {
                const componentType = component.types[0];
                switch (componentType) {
                    case 'postal_code': {
                        postcode = `${component.long_name}${postcode}`;
                        break;
                    }
                    case "postal_code_suffix": {
                        postcode = `${postcode}-${component.long_name}`;
                        break;
                    }
                    case "locality": { // city
                        $( '#billing-city_billing-add' ).val( component.long_name );
                        break;
                    }
                    case "administrative_area_level_1": { // region
                        $( '#billing-region_billing-add' ).val( component.short_name );
                        break;
                    }
                }
            }

            $( '#billing-postcode_billing-add' ).val( postcode );

            // format the main address field
            $autocompleteInput.val( result.formatted_address );

        }

    },
    ['jQuery']
);