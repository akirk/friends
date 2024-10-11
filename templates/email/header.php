<?php
/**
 * This template contains the HTML header for HTML e-mails.
 *
 * @version 1.0
 * @package Friends
 */

?>
<!doctype html>
<html>
<head>
	<style type="text/css" media="all">
		body {
			font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
			background-color: #f6f6f6;
			margin: 2em;
			padding: 0;
			color: #48427c;
		}
		div.content {
			background-color: #fff;
			padding: 1em 2em;
		}
		@media only screen and ( max-width: 800px ) {
			body {
				margin: 0;
			}
			div.content {
				padding: 1em;
			}
		}
		a:hover {
			color: #1341d4;
			text-decoration: underline;
		}
		a {
			text-decoration: none;
			color: #0088cc;
		}
		a.author {
			text-decoration: none;
			color: #48427c;
		}
		h2 {
			font-size: 1.6rem;
			font-weight: 500;
			line-height: 1.1;
			margin-bottom: 0;
		}
		blockquote {
			border-left: .2rem solid #f6f6fa;
			margin-left: 0;
			padding: .2rem .4rem;
		}
		blockquote p {
			margin-top: .2rem;
		}
		img {
			object-fit: contain;
		}

		figure img, img.size-full {
			max-width: 100% !important;
			height: auto !important;
		}
		figcaption {
			text-align: center;
			font-size: .9rem;
		}

		div.subscription-settings {
			color: #999;
			font-size: .9rem;
		}
		div.post-meta {
			margin-bottom: 2em;
		}
		div.post-footer {
			margin-top: 2em;
			margin-bottom: 2em;
		}
		div.footer {
			margin-top: 1em;
			margin-bottom: 1em;
			color: #999;
			text-align: center;
			clear: both;
			font-size: .9rem;
		}
		p.permalink {
			font-size: .8rem;
		}

		code, pre {
			overflow: auto;
			overflow-wrap: break-word;
		}

		mark {
			background-color: #ff0;
			color: #000;
		}

		hr {
			border: 0;
			margin-top: 1.5em;
			margin-bottom: 1.5em;
			border-top: 1px solid #999;
			width: 80%;
		}
		a.btn {
			background: #fff;
			border: .05rem solid #2e5bec;
			border-radius: .1rem;
			color: #2e5bec;
			cursor: pointer;
			display: inline-block;
			font-size: 1rem;
			line-height: 1.2rem;
			outline: none;
			padding: .5em .4rem;
			text-align: center;
			text-decoration: none;
			vertical-align: middle;
			white-space: nowrap;
			margin-right: 1em;
		}
		a.btn:hover {
			background: #dde5fc;
			border-color: #2050eb;
			text-decoration: none;
		}
		a.btn.noborder {
			border-color: transparent;
		}
		div.footer a, div.subscription-settings a {
			color: #999;
			text-decoration: underline;
		}
	</style>
	<title><?php echo esc_html( $args['email_title'] ); ?></title>
	<!--[if gte mso 12]>
	<style type="text/css" media="all">
	body {
	font-family: arial;
	font-size: 0.8em;
	}
	.post, .comment {
	background-color: white !important;
	line-height: 1.4em !important;
	}
	</style>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
	<div class="content">
