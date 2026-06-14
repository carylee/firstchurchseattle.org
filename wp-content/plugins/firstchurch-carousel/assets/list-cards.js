/* Carousel Cards list screen: render each row's Preview cell as a scaled-down
 * card thumbnail via the shared renderer, from the item JSON the column emits.
 * So the list looks like the curator instead of a plain text table. */
import { buildStageEl } from '@fccar/stage';

( function () {
	'use strict';

	var wells = document.querySelectorAll( '.fccar-list-thumb' );
	for ( var i = 0; i < wells.length; i++ ) {
		var well = wells[ i ];
		var item;
		try { item = JSON.parse( well.getAttribute( 'data-fccar' ) || '{}' ); }
		catch ( e ) { continue; }
		var stage = buildStageEl( item, {} );
		well.appendChild( stage );
		var w = well.clientWidth || 160;
		stage.style.transform = 'scale(' + ( w / 1280 ) + ')';
	}
}() );
