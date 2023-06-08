;'use strict';
function FCP_Forms_Popup(section) {

    if ( typeof section === 'string' ) {
        this.section = document.querySelector( section );
    } else if ( section instanceof jQuery ) {
        this.section = section[0];
    } else {
        this.section = section;
    }
    
    if ( !this.section ) {
        this.show = this.hide = function(){};
        return;
    }

    var self = this;

    this.show = function(target) {
        this.section.classList.add( 'fcp-active' );
        document.querySelector( 'body' ).style.overflow = 'hidden';
        document.addEventListener( 'keydown', enter_press );
        document.addEventListener( 'keydown', esc_press );
        presave_values();
        this.section.querySelector( 'input, button, select, textarea' ).focus();
        if ( typeof target === 'object' ) {
            self.target = target;
        } else {
            delete self.target;
        }
        this.section.dispatchEvent( new CustomEvent( 'popup_opened' ) );
    };

    this.hide = function() {
        this.section.classList.remove( 'fcp-active' );
        document.querySelector( 'body' ).style.overflow = null;
        document.removeEventListener( 'keydown', enter_press );
        document.removeEventListener( 'keydown', esc_press );
        if ( typeof self.target !== 'undefined' ) {
            self.target.focus();
        }
        this.section.dispatchEvent( new CustomEvent( 'popup_closed' ) );
    };

    // close buttons
    var apply = document.createElement( 'button' );
    apply.title = 'Apply';
    apply.type = 'button';
    apply.className = 'fcp-section--close fcp-section--apply';
    apply.addEventListener( 'click', function() {
       self.hide();
    });
    this.section.appendChild( apply );

    var discard = document.createElement( 'button' );
    discard.title = 'Discard';
    discard.type = 'button';
    discard.className = 'fcp-section--close fcp-section--discard';
    discard.addEventListener( 'click', function() {
       self.hide();
       restore_values();
    });
    this.section.appendChild( discard );

    function enter_press(e) {
        if ( e.code === 'Enter' ) {
            if ( !~['input','button','select','textarea'].indexOf( e.target.nodeName.toLowerCase() ) ) { return true; }
            e.preventDefault();
            self.hide();
            return false;
        }
    }
    this.enter_press = enter_press;

    function esc_press(e) {
        if ( e.code === 'Escape' ) {
            e.preventDefault();
            self.hide();
            restore_values();
            return false;
        }
    }
    this.esc_press = esc_press;

    function presave_values() {
        self.section.querySelectorAll( 'input, button, select, textarea' ).forEach( function(a) {
            a.setAttribute( 'data-presaved-value', a.value );
        });
    }
    function restore_values() {
        self.section.querySelectorAll( 'input, button, select, textarea' ).forEach( function(a) {
            a.value = a.getAttribute( 'data-presaved-value' );
            a.removeAttribute( 'data-presaved-value' );
        });
    }

}