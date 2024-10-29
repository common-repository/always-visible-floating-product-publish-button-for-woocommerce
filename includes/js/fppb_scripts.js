/**
 * Scripts for Floating Product Publish Button for WooCommerce
 * Version:     2.1.0
 * Author:      andre-dane-dev<andre.dane.dev@gmail.com>
 * License:     GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (C) 2022 andre-dane-dev
 */
( function( $ ) {

    /**
     * @since 1.1.0
     */
    $( window ).on( 'load', fppbInit );

    /**
     * Initialize event listeners.
     *
     * @since   2.0.0
     * @version 2.1.0
     *
     * @return {void}
     */
    function fppbInit() {
        // Esc if not wp admin
        if ( ! $( '.wp-admin' ).length ) return;
        if (
            $( '.woocommerce_page_wc-settings' ).length
            || $( '.settings_page_fppb' ).length
        ) {
            // Toggle custom fields
			if ( $( '#fppb_default_position' ) ) {
                fppbToggleCustomFields();
                
				$( '#fppb_default_position' ).on( 'change', fppbToggleCustomFields );
			}
        } else if ( 
            $( '#publish' ).length
            && (
                $( '.post-type-product' ).length
                || (
                    ! $( '.block-editor-page' ).length
                    && ( $( '.post-type-page' ).length || $( '.post-type-post' ).length )
                )
            )
        ) {
            // Run functions
            $( window ).on( 'scroll', fppbFixZindexBug );
            $( window ).on( 'scroll', fppbMakePublishButtonFloating );
        } else {
            return;
        }
    }

    /**
     * Toggle custom fields: show custom fields only if default position is set to 'custom'.
     *
     * @since  2.0.0
     *
     * @return {void}
     */
    function fppbToggleCustomFields() {
        var defaultPosition    = $( '#fppb_default_position' ),
            customTopPosition  = $( '#fppb_custom_top_position' ).closest( 'tr' ),
            customLeftPosition = $( '#fppb_custom_left_position' ).closest( 'tr' );

        if ( 'custom' == defaultPosition.val() ) {
            customTopPosition.css( 'display', '' );
            customLeftPosition.css( 'display', '' );
        } else {
            // Hide fields
            customTopPosition.css( 'display', 'none' );
            customLeftPosition.css( 'display', 'none' );
            // Clean inputs
            customTopPosition.find( 'input' ).val( '' );
            customLeftPosition.find( 'input' ).val( '' );
        }
    }

    /**
     * Fix z-index bug: always keep buttons in the foreground.
     *
     * @since   1.0.0
     * @version 1.1.0
     *
     * @return {void}
     */
    function fppbFixZindexBug() {
        if ( $( '#side-sortables' ).css( 'position' ) != 'absolute' ) {
            $( '#side-sortables' ).css( 'z-index', 1000 );
        }
    }

    /**
     * Make the buttons floating.
     *
     * @since   1.0.0
     * @version 1.1.0
     *
     * @return  {void}
     */
    function fppbMakePublishButtonFloating() {
        // Get buttons
        var publishBtn = $( '#publish' );
        var saveDraft  = $( '#save-action input#save-post' );

        if ( ! publishBtn.length ) return;

        // Create container element
        const fppbBtnContainer = $( document.createElement( 'div' ) );
        fppbBtnContainer.attr( 'id', 'fppb_btn_container' );
        fppbBtnContainer.append( publishBtn );

        // Append container
        fppbBtnContainer.appendTo( '#publishing-action' );

        // Check if there is also save-post
        if ( saveDraft.length ) {
            fppbBtnContainer.append( saveDraft );
            fppbBtnContainer.addClass( 'fppb_has_save_draft' );
        } else {
            fppbBtnContainer.addClass( 'fppb_publish' );
        }

        // Make container draggable
        fppbMakePublishButtonDraggable( fppbBtnContainer );

        // Set default position
        fppbSetDefaultPosition( fppbBtnContainer );

        // Set style
        fppbSetButtonStyles( publishBtn, saveDraft );

        // Execute only once
        $( window ).off( 'scroll', fppbMakePublishButtonFloating );
    }

    /**
     * Make element draggable.
     *
     * @since   1.0.0
     * @version 1.1.0
     *
     * @see     https://www.w3schools.com/HOWTO/howto_js_draggable.asp
     * @param   {jQuery} container
     * @return  {void}
     */
    function fppbMakePublishButtonDraggable( container ) {
        const pixelRange = 10;
        let xPos,
            yPos;

        container.on( 'mousedown', function( e ) {
            // If container has default position, remove classes in order to override position attributes.
            if (
                ! fppb_options['fppb_has_custom_position']
                && container.hasClass( 'fppb-' + fppb_options['fppb_top_position'] )
                && container.hasClass( 'fppb-' + fppb_options['fppb_left_position'] )
            ) {
                fppbRemoveDefaultPosition( container )
            }

            var pos1 = 0,
                pos2 = 0,
                pos3 = 0,
                pos4 = 0;

            xPos = e.pageX;
            yPos = e.pageY;

            // Store the initial position
            element = $( this );
            element.off( 'click' );
            e = e || window.event;
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;

            /**
             * Controls whether the click on the element is a single click or a long click for dragging.
             *
             * @see https://stackoverflow.com/a/59741870
             */
            document.onmouseup = function ( e ) {
                const xDiff = Math.abs( e.pageX - xPos),
                      yDiff = Math.abs( e.pageY - yPos);

                if ( xDiff < pixelRange && yDiff < pixelRange ) {
                    element.off( 'click' );
                }
                // document.onmouseup = null;
                document.onmousemove = null;
            };

            // Update element's position
            document.onmousemove = function ( e ) {
                element.on( 'click', function( e ) {
                    e.preventDefault();
                } );
                e = e || window.event;
                e.preventDefault();
                // Get updated position
                pos1 = pos3 - e.clientX;
                pos2 = pos4 - e.clientY;
                pos3 = e.clientX;
                pos4 = e.clientY;
                // Update element's position
                element.css( 'top', ( element.position().top - pos2 ) + 'px');
                element.css( 'left', ( element.position().left - pos1 ) + 'px' );
            };
        } );
    }

    /**
     * Remove default position.
     *
     * @since  1.1.0
     *
     * @param  {jQuery} container
     * @return {void}
     */
    function fppbRemoveDefaultPosition( container ) {
        // Remove default position, otherwise can't move
        if ( ! fppb_options['fppb_has_custom_position'] ) {
            var defaultTop = fppb_options['fppb_top_position'],
                defaultLeft = fppb_options['fppb_left_position'];

            if ( ! defaultTop || ! defaultLeft ) return;

            // Get current position
            var top = container.css('top'),
                left = container.css('left');

            // Override position
            container.removeClass( 'fppb-' + defaultTop );
            container.removeClass( 'fppb-' + defaultLeft );
            container.css( 'top', top );
            container.css( 'left', left );
        }
    }

    /**
     * Set buttons container default position.
     *
     * @since  1.1.0
     *
     * @param  {jQuery} container
     * @return {void}
     */
    function fppbSetDefaultPosition( container ) {
        var top  = fppb_options['fppb_top_position'],
            left = fppb_options['fppb_left_position'];

        if ( ! top || ! left ) return;

        // Set attributes if has custom posiiton
        if ( fppb_options['fppb_has_custom_position'] ) {
            container.css({
                top: top,
                left: left,
            });
        } else { // add classes if has defualt position
            container.addClass( 'fppb-' + top );
            container.addClass( 'fppb-' + left );
        }
    }

    /**
     * Set Button Styles.
     *
     * @since  1.1.0
     *
     * @param  {jQuery} publish
     * @param  {jQuery} saveDraft
     * @return {void}
     */
    function fppbSetButtonStyles( publish, saveDraft ) {
        var color           = fppb_options['fppb_color'],
            backgroundColor = fppb_options['fppb_background_color'],
            borderColor     = fppb_options['fppb_border_color'],
            shadowColor     = fppb_options['fppb_shadow_color'];

        // Set colors
        if ( color && backgroundColor ) {
            publish.css({
                color: color,
                background: backgroundColor,
            });

            if ( saveDraft.length ) {
                saveDraft.css({
                    color: backgroundColor,
                    background: color,
                });
            }
        }

        // Set border color
        if ( borderColor ) {
            publish.css( 'border-color', borderColor );

            if ( saveDraft.length ) {
                saveDraft.css( 'border-color', borderColor );
            }
        }

        // Set box shadow
        if ( shadowColor ) {
            publish.css( 'box-shadow', '1px 1px 10px ' + shadowColor );

            if ( saveDraft.length ) {
                saveDraft.css( 'box-shadow', '1px 1px 10px ' + shadowColor );
            }
        }
    }
} ) ( jQuery );
