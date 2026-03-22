( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;
	var SSR = serverSideRender;

	var sidebarBlocks = [
		{ name: 'friends/stats', title: 'Friend Stats', icon: 'chart-area' },
		{ name: 'friends/refresh', title: 'Friend Posts Refresh', icon: 'update' },
		{ name: 'friends/post-formats', title: 'Post Formats', icon: 'filter' },
		{ name: 'friends/add-subscription', title: 'Add Subscription', icon: 'plus-alt' },
		{ name: 'friends/starred-friends-list', title: 'Starred Friends', icon: 'star-filled' },
		{ name: 'friends/search', title: 'Friends Search', icon: 'search' },
		{ name: 'friends/feed-title', title: 'Feed Title', icon: 'admin-site-alt3' },
		{ name: 'friends/feed-chips', title: 'Feed Chips', icon: 'tag' },
		{ name: 'friends/post-content', title: 'Friend Post Content', icon: 'text-page' },
		{ name: 'friends/post-permalink', title: 'Post Permalink', icon: 'admin-links' },
		{ name: 'friends/author-star', title: 'Author Star', icon: 'star-empty' },
		{ name: 'friends/author-avatar', title: 'Author Avatar', icon: 'admin-users' },
		{ name: 'friends/author-name', title: 'Author Name', icon: 'nametag' },
		{ name: 'friends/author-description', title: 'Author Description', icon: 'editor-paragraph' },
		{ name: 'friends/author-chips', title: 'Author Chips', icon: 'tag' },
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
