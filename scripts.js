!function(){let a=setInterval(function(){let b=document.readyState;if(b!=='complete'&&b!=='interactive'||typeof jQuery==='undefined'){return}let $=jQuery;clearInterval(a);a=null;

    // style the input elements
    var s = {
        "file" : ".fcp-form input[type=file]",
        "select" : ".fcp-form select",
        "button" : ".fcp-form button",
        "empty_class" : "fcp-form-empty"
    };
    
    // change the content of file lable
    $( s.file ).on( 'change', function() {
        var $self = $( this ),
            $label = $self.next( 'label' );
        empty_file( $self );
        if ( $self[0].files.length === 0 ) {
            var label = $self.prop( 'multiple' ) ?
                $self.attr( 'data-select-files' ) :
                $self.attr( 'data-select-file' );
            $label.html( label );
            return;
        }
        if ( $self[0].files.length === 1 ) {
            $label.html( $self[0].files[0]['name'] );
            return;
        }
        $label.html( $self[0].files.length + ' ' + $self.attr( 'data-files-selected' ) );
    });
    
    // change the style of empty select
    $( s.select ).on( 'change', function() {
        empty_select( $( this ) );
    });

    
    // placeholder replacement on init
    $( s.file ).each( function() {
        empty_file( $( this ) );
    });
    $( s.select ).each( function() {
        empty_select( $( this ) );
    });
    $( s.button ).each( function() {
        empty_button( $( this ) );
    });
    
    function empty_file($self) {
        if ( $self[0].files.length === 0 ) {
            $self.addClass( s.empty_class );
            return;
        }
        $self.removeClass( s.empty_class );
    }
    function empty_select($self) {
        if ( $self.children( 'option:selected' ).val() === '' ) {
            $self.addClass( s.empty_class );
            return;
        }
        $self.removeClass( s.empty_class );
    }
    function empty_button($self) {
        $self.addClass( s.empty_class );
    }

}, 300 )}();
