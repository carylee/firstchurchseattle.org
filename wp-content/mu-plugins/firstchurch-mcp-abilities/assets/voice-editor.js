/**
 * "Rewrite in church voice" — a Rich Text toolbar button for the block editor.
 *
 * Rewrites the selected text (or the whole field if nothing is selected) by
 * POSTing to /firstchurch/v1/voice/rewrite, which runs the firstchurch/
 * rewrite-in-voice ability on the core AI Client with the house voice. No build
 * step: plain JS against the wp.* globals.
 */
( function ( wp ) {
	if ( ! wp || ! wp.richText || ! wp.blockEditor ) {
		return;
	}
	var registerFormatType = wp.richText.registerFormatType;
	var getTextContent = wp.richText.getTextContent;
	var slice = wp.richText.slice;
	var create = wp.richText.create;
	var insert = wp.richText.insert;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	function RewriteInVoice( props ) {
		var value = props.value;
		var onChange = props.onChange;
		var state = useState( false );
		var busy = state[ 0 ];
		var setBusy = state[ 1 ];

		// Selection if there is one; otherwise the whole field.
		var hasSelection = value.start !== value.end;
		var start = hasSelection ? value.start : 0;
		var end = hasSelection ? value.end : value.text.length;
		var selected = getTextContent( slice( value, start, end ) );

		return el( RichTextToolbarButton, {
			icon: 'welcome-write-blog',
			title: busy ? __( 'Rewriting…', 'firstchurch' ) : __( 'Rewrite in church voice', 'firstchurch' ),
			isActive: false,
			isDisabled: busy || ! selected.trim(),
			onClick: function () {
				if ( ! selected.trim() ) {
					return;
				}
				setBusy( true );
				apiFetch( {
					path: '/firstchurch/v1/voice/rewrite',
					method: 'POST',
					data: { text: selected, kind: 'selection' },
				} )
					.then( function ( res ) {
						var rewritten = res && res.text ? String( res.text ).trim() : '';
						if ( rewritten ) {
							onChange( insert( value, create( { text: rewritten } ), start, end ) );
						}
					} )
					.catch( function ( e ) {
						window.alert(
							__( 'Rewrite failed: ', 'firstchurch' ) +
								( e && e.message ? e.message : 'unknown error' )
						);
					} )
					.finally( function () {
						setBusy( false );
					} );
			},
		} );
	}

	registerFormatType( 'firstchurch/rewrite-in-voice', {
		title: __( 'Rewrite in church voice', 'firstchurch' ),
		// Action button only — we replace text on click and never toggle this
		// format, so the tag/class are never actually applied to content.
		tagName: 'span',
		className: 'firstchurch-rewrite-voice',
		edit: RewriteInVoice,
	} );
} )( window.wp );
