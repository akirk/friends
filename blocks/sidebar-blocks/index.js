( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;
	var SSR = serverSideRender;

	var sidebarBlocks = [
		{ name: 'friends/stats', title: 'Friend Stats', icon: 'chart-area' },
		{ name: 'friends/refresh', title: 'Friend Posts Refresh', icon: 'update' },
		{ name: 'friends/post-formats', title: 'Post Formats', icon: 'filter' },
		{ name: 'friends/add-subscription', title: 'Add Subscription', icon: 'plus-alt' },
		{ name: 'friends/search', title: 'Friends Search', icon: 'search' },
		{ name: 'friends/feed-header', title: 'Feed Header', icon: 'admin-site-alt3' },
		{ name: 'friends/post-entry', title: 'Friend Post Entry', icon: 'format-aside' },
		{ name: 'friends/author-header', title: 'Author Header', icon: 'businessperson' },
	];

	sidebarBlocks.forEach( function ( block ) {
		blocks.registerBlockType( block.name, {
			title: block.title,
			icon: block.icon,
			category: 'widgets',
			edit: function () {
				return el( SSR, { block: block.name } );
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
