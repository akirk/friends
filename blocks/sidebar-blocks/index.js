( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;
	var SSR = serverSideRender;

	var listSupports = {
		color: { background: true, text: true, link: true },
		typography: { fontSize: true, lineHeight: true },
		spacing: { padding: true, margin: true },
	};

	var chipSupports = {
		color: { background: true, text: true, link: true },
		typography: { fontSize: true },
		spacing: { padding: true, margin: true },
	};

	var headingSupports = {
		color: { background: true, text: true, link: true },
		typography: { fontSize: true, lineHeight: true },
	};

	var sidebarBlocks = [
		{ name: 'friends/stats', title: 'Friend Stats', icon: 'chart-area', supports: listSupports },
		{ name: 'friends/refresh', title: 'Friend Posts Refresh', icon: 'update', supports: listSupports },
		{ name: 'friends/post-formats', title: 'Post Formats', icon: 'filter', supports: listSupports },
		{ name: 'friends/add-subscription', title: 'Add Subscription', icon: 'plus-alt', supports: listSupports },
		{ name: 'friends/search', title: 'Friends Search', icon: 'search' },
		{ name: 'friends/feed-title', title: 'Feed Title', icon: 'admin-site-alt3', supports: headingSupports },
		{ name: 'friends/feed-chips', title: 'Feed Chips', icon: 'tag', supports: chipSupports },
		{ name: 'friends/post-content', title: 'Friend Post Content', icon: 'text-page' },
		{ name: 'friends/post-permalink', title: 'Post Permalink', icon: 'admin-links', supports: listSupports },
		{ name: 'friends/post-reblog', title: 'Reblog Button', icon: 'controls-repeat' },
		{ name: 'friends/post-boost', title: 'Boost Button', icon: 'controls-repeat' },
		{ name: 'friends/post-reactions', title: 'Reactions', icon: 'star-filled' },
		{ name: 'friends/post-comments', title: 'Comments', icon: 'admin-comments' },
		{ name: 'friends/author-star', title: 'Author Star', icon: 'star-empty' },
		{ name: 'friends/author-avatar', title: 'Author Avatar', icon: 'admin-users' },
		{ name: 'friends/author-name', title: 'Author Name', icon: 'nametag', supports: headingSupports },
		{ name: 'friends/author-description', title: 'Author Description', icon: 'editor-paragraph', supports: listSupports },
		{ name: 'friends/author-chips', title: 'Author Chips', icon: 'tag', supports: chipSupports },
	];

	sidebarBlocks.forEach( function ( block ) {
		var config = {
			title: block.title,
			icon: block.icon,
			category: 'widgets',
			edit: function () {
				return el( SSR, { block: block.name } );
			},
			save: function () {
				return null;
			},
		};
		if ( block.supports ) {
			config.supports = block.supports;
		}
		blocks.registerBlockType( block.name, config );
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
