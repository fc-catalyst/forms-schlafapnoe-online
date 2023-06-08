!function(){let a=setInterval(function(){let b=document.readyState;if(b!=='complete'&&b!=='interactive'||typeof jQuery==='undefined'){return}let $=jQuery;clearInterval(a);a=null;

    fcLoadScriptVariable(
        window.fcp_forms_assets_url + 'advisor.js',
        'FCP_Advisor',
        function() {
            FCP_Advisor( $( '#specialty_entity-search-home' ), window.fcp_forms_data.specialties );
            FCP_Advisor( $( '#place_entity-search-home' ), window.fcp_forms_data.places );
        },
        [],
        true
    );

},300 )}();
