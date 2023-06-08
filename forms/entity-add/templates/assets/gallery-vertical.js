/*
 * Change a vertical block with images to a specific gallery
 * in later version make it work with multiple galleries
 */

function fcAddGallery(selector = '') {

    if ( !selector ) {
        selector = '.fct1-vg';
    }

    var $ = jQuery,
        $self = $( selector );

    if ( !$self.length ) { return }

    var $children = $self.children();
        
    $self.addClass( 'fct1-vg' );
    $self.parent().addClass( 'fct1-vg-wrap' );

    // navigation buttons
    $self.before( '<div class="fct1-vg-up"></div>' );
    $self.after( '<div class="fct1-vg-down"></div>' );
    var $up = $self.prev( '.fct1-vg-up' ),
        $down = $self.next( '.fct1-vg-down' );
    
    $down.click( function() {
        var visible_height = Math.round( $self.parent().innerHeight() - $self.position()['top'] );

        if ( visible_height > $self.outerHeight() ) { return }

        var cheights = [],
            vpheight = Math.max( document.documentElement.clientHeight || 0, window.innerHeight || 0 ),
            offset_now = Math.round( Number( $self.attr( 'data-offset' ) ? $self.attr( 'data-offset' ) : 0 ) ),
            offset_max_step = Math.min.apply( null, [
                Math.round( visible_height * 3 / 4 ),
                Math.round( vpheight * 3 / 4 )
            ]),
            new_step = 0,
            cheight_above = visible_height + offset_now;
        
        $children.each( function(a, b) { cheights[a] = Math.floor( $( b ).outerHeight( true ) ); } );
        
        // count new offset
        for ( let i = 0, a = 0, add = 0; i < cheights.length; i++ ) {
            a = cheights[i];
            add += a;

            if ( add <= cheight_above ) { continue }

            new_step = add - cheight_above;

            if ( new_step > offset_max_step && new_step !== a ) {
                new_step = new_step - a;
                break;
            }
            
            if ( i === cheights.length - 1 ) {
                $down.hide();
            }
        }
        $up.show();

        if ( !new_step ) { return }

        fcAddGallery.process( offset_now + new_step );

    });

    $up.click( function() {

        fcAddGallery.process( 0 );
        
        $up.hide();
        $down.show();
    });
    
    // the motion process
    fcAddGallery.process = function(offset_new) {
        $children.each( function( a, b ) {
            $( b ).css( 'transform', 'translate( 0, -' + offset_new + 'px )' );
        });
        $self.attr( 'data-offset', offset_new );
    };

    // navigation buttons show-hide
    fcAddGallery.reset = function() {
        fcAddGallery.process( 0 );
        
        $up.hide();
        $down.hide();
        if ( $self.parent().innerHeight() - $self.position()['top'] > $self.outerHeight() ) { return }
        $down.show();
    };
    $up.hide();
    $down.hide();
    setTimeout( fcAddGallery.reset );
    $( window ).resize( function() { fcAddGallery.reset(); });
    // ++add body resize event too, as there are some moving parts

}