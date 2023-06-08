fcLoadScriptVariable(
    window.fcp_forms_assets_url + 'advisor.js',
    'FCP_Advisor',
    function() {
        let $ = jQuery;
        FCP_Advisor( $( '#specialty_entity-search' ), window.fcp_forms_data.specialties );
        FCP_Advisor( $( '#place_entity-search' ), window.fcp_forms_data.places );
    },
    ['jQuery'],
    true
);