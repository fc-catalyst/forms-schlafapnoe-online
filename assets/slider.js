;'use strict';

function FCP_Slider(selector, options) {
    if ( !selector ) {
        return;
    }

    var root_element = document.querySelector( selector );
    if ( !root_element ) {
        return;
    }
    var slider = root_element,
        slides_number = slider.childElementCount;

    [].slice.call( slider.children ).forEach( function(a) {
        if ( a.classList.contains( 'fcp-slide-hidden' ) ) {
            slides_number--;
        }
    });

    slider_layout();

    var events_to_listen = {
        "touchstart"    : ["touchmove", "touchend"],
        "mousedown"     : ["mousemove", "mouseup"]
    };

    // applying event listeners
    for ( var name in events_to_listen ) {
        slider.addEventListener( name, function f(e) {
            operate_event_listeners( e, f );
        });
    }
    
    window.addEventListener( 'resize', function() {
        finish_up( scroll_x(), 0 ); // --it doesn't keep the original slide, just slides to the closest one
    });

    function finish_up(start, start_time) {
        var scrolled = scroll_x(),
            width = slider.getBoundingClientRect().width,
            reminder = scrolled % width,
            scroll_by = reminder > width / 2 ? width - reminder : -reminder;
        
        // swipe
        if ( start_time && new Date().getTime() - start_time < 400 && Math.abs( start - scrolled ) > 100 ) {
            scroll_by = scrolled - start > 0 ? width - reminder : -reminder;
        }

        slider.classList.add( 'fcp-slider-smooth' );
        scroll_x( scrolled + scroll_by );
    }

    function operate_event_listeners(e, f) {

        var pointer = pointer_type( e ),
            start = pointer_x( e ),
            start_time = new Date().getTime(),
            scrolled = scroll_x(),
            event_move = events_to_listen[e.type][0],
            event_up = events_to_listen[e.type][1];

        window.addEventListener( event_move, follow_pointer );
        window.addEventListener( event_up, function self(e) {
            this.removeEventListener( event_move, follow_pointer, false );
            this.removeEventListener( event_up, self, false );
            finish_up( scrolled, start_time ); // move slider to the closest position or swipe
        });
        
        function follow_pointer(e) {
            slider.classList.remove( 'fcp-slider-smooth' );
            // already scrolled X + start point X - current pointer X
            scroll_x( scrolled + start - pointer_x( e, pointer ) );
        };

    }

    function pointer_type(e) {
        return ~e.type.indexOf( 'touch' ) ? 'touch' : 'cursor';
    }
    
    function pointer_x(e, pointer) {
        if ( !pointer ) {
            pointer = pointer_type( e );
        }
        if ( pointer === 'touch' ) {
            return ~~e.changedTouches[0].clientX;
        }
        return ~~e.pageX;
    }

    function scroll_x(a) {
        if ( typeof a === 'undefined' ) {
            return slider.scrollLeft;
        }
        if ( !isNaN( a ) ) {
            slider.scrollLeft = a;
        }
    }

    function slider_layout() {
        // wrap into a long div and the main holder
        var wrapper_width = slides_number * 100;
        slider.innerHTML = '<div class="fcp-slider">' +
                            '<div class="fcp-slides-wrap" style="width:' + wrapper_width + '%">' +
                            slider.innerHTML +
                            '</div></div>';
        slider = root_element.firstElementChild;
        slider.classList.add( 'fcp-slider-smooth' );

        /* the slider can work withoug the following, including the navigation functions */

        // add navigation & progress bar
        if ( !options || typeof options !== 'object' ) {
            return;
        }
        
        root_element.innerHTML = '<div class="fcp-slider-holder">' +
                                            root_element.innerHTML +
                                            '</div>';
        slider = root_element.querySelector( '.fcp-slider' );
        var holder = slider.parentElement;
        
        if ( options.navigation ) {

            // events for external navigation
            root_element.addEventListener( 'fcp-slide-next', function(e) { slide_next() });
            root_element.addEventListener( 'fcp-slide-prev', function(e) { slide_prev() });

            if ( !Array.isArray( options.navigation ) ) {
                return;
            }

            var add_tracking = {}; // collect operations to perform with scroll_x
                add_tracking.operations = [],
                add_tracking.abort = false;

            options.nav = {}; // simplify further check ifs
            options.navigation.forEach( function(a) {
                if ( typeof a !== 'string' ) {
                    return;
                }
                options.nav[a] = true;
            });
            
            // arrows navigation
            if ( options.nav.arrows ) {
                var arrow = {};

                holder.appendChild( arrow.prev = document.createElement( 'button' ) );
                arrow.prev.classList.add( 'fcp-slide-prev' );
                arrow.prev.addEventListener( 'click', slide_prev );

                holder.appendChild( arrow.next = document.createElement( 'button' ) );
                arrow.next.classList.add( 'fcp-slide-next' );
                arrow.next.addEventListener( 'click', slide_next );
                
                if ( !options.nav.loop ) {
                    arrow.prev.classList.add( 'stop' );
                    add_tracking.operations.push( function(a) {
                        arrow.prev.classList.remove( 'stop' );
                        arrow.next.classList.remove( 'stop' );
                        if ( slide_target( a ) === 1 ) {
                            arrow.prev.classList.add( 'stop' );
                        }
                        if ( !slide_exists( slide_target( a ) + 1 ) ) {
                            arrow.next.classList.add( 'stop' );
                        }
                    });
                }
            }

            // dots navigation
            if ( options.nav.dots ) {
                var dots = {};
                    dots.group = document.createElement( 'div' );

                dots.group.classList.add( 'fcp-slide-dots' );
                for ( var i = 1, j = slides_number; i <= j; i++ ) { 
                    dots.group.appendChild( dots[i] = document.createElement( 'button' ) );
                    dots[i].addEventListener( 'click', function(e) { var j = i;
                        return function(e) {
                            e.preventDefault();
                            slide_to( j );
                        }
                    }());
                }
                holder.appendChild( dots.group );
                delete dots.group;
                dots[1].classList.add( 'active' );
                
                add_tracking.operations.push( function(a) {
                    if ( slide_next_blocked && slide_now() < slide_target( a ) ) { return; }
                    if ( !slide_exists( slide_target( a ) ) ) { return; }
                    for ( var i in dots ) {
                        dots[i].classList.remove( 'active' );
                    }
                    dots[ slide_target( a ) ].classList.add( 'active' );
                });
            }
            
            // blocking next slides
            if ( options.nav.can_block ) {

                root_element.addEventListener( 'fcp-slide-next-block', function(e) { slide_next_block() });
                root_element.addEventListener( 'fcp-slide-next-free', function(e) { slide_next_free() });

                add_tracking.operations.push( function(a) {
                    if ( slide_next_blocked && slide_now() < slide_target( a ) ) {
                        add_tracking.abort = ( slide_now() - 1 )  * slider_width();
                    }
                });
                // -- slow pointer scrolling jumps after half

            }
            
            // override the main scrolling function to perform some additional operations
            if ( add_tracking.operations.length ) {

                var scroll_x_init = scroll_x;
                scroll_x = function(a) {
                    if ( typeof a === 'undefined' ) {
                        return scroll_x_init( a );
                    }
                    add_tracking.operations.forEach( function(f) { f( a ) });
                    a = add_tracking.abort === false ? a : add_tracking.abort;
                    scroll_x_init( a );
                    add_tracking.abort = false;
                }
            }
        }

    }
    
    // navigation functions
    function slide_next(e) {
        if ( e ) { e.preventDefault() }
        var n = slide_now() + 1;
        if ( options && options.nav && options.nav.loop ) {
            n = slide_exists( n ) ? n : 1;
        }
        slide_to( n );
    }

    function slide_prev(e) {
        if ( e ) { e.preventDefault() }
        var n = slide_now() - 1;
        if ( options && options.nav && options.nav.loop ) {
            n = slide_exists( n ) ? n : slides_number;
        }
        slide_to( n );
    }

    function slider_width() {
        return slider.getBoundingClientRect().width;
    }
    function slides_wrap_width() {
        return slider.firstElementChild.getBoundingClientRect().width;
    }
    function slide_exists(n) {
        return n > 0 && n <= slides_number;
    }
    function slide_now() {
        return Math.round( scroll_x() / slider_width() ) + 1;
    }
    function slide_to(n) { // the first slide is 1
        if ( !n || isNaN( n ) ) {
            return;
        }
        n--;
        scroll_x( slider_width() * n );
    }
    function slide_target(a) { // slide number by new left position
        return Math.round( a / slider_width() ) + 1;
    }

    var slide_next_blocked = false;
    function slide_next_block() {
        slide_next_blocked = true;
        console.log( 'blocked', slide_next_blocked );
    }
    function slide_next_free() {
        slide_next_blocked = false;
        console.log( 'blocked', slide_next_blocked );
    }

}
