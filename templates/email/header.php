<?php
/**
 * This template contains the HTML header for HTML e-mails.
 *
 * @package Friends
 */

?>
<!doctype html>
<html>
<head>
	<style type="text/css" media="all">
		a:hover { color: red; }
		a {
			text-decoration: none;
			color: #0088cc;
		}
		blockquote {
			width: 80%;
			padding-left: 1em;
			margin-left: 0;
			border: 0;
			border-left: 2px solid #cccccc;
		}
	</style>
	<title><?php echo $email_title; ?></title>
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
<body style="margin: 2em; padding: 0; width: 100% !important;">
