<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $page_title ); ?> &mdash; <?php echo esc_html( $blog_name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_attr( $style_url ); ?>">
</head>
<body>
	<header class="site-header">
		<a href="<?php echo esc_attr( $index_url ); ?>"><?php echo esc_html( $blog_name ); ?></a>
		<?php if ( $blog_description ) : ?>
		<p class="site-description"><?php echo esc_html( $blog_description ); ?></p>
		<?php endif; ?>
	</header>
	<main>
		<article>
			<?php if ( $post_title ) : ?>
			<h1><?php echo esc_html( $post_title ); ?></h1>
			<?php endif; ?>
			<div class="post-meta">
				<a href="<?php echo esc_attr( $year_archive_url ); ?>"><time datetime="<?php echo esc_attr( $post_date_iso ); ?>"><?php echo esc_html( $post_date ); ?></time></a>
				<span class="post-author"><?php echo esc_html( $post_author ); ?></span>
			</div>
			<?php echo $content; // Already processed HTML. ?>
		</article>
		<nav class="post-nav">
			<span>
			<?php
			if ( $prev_link ) {
				echo $prev_link;
			}
			?>
			</span>
			<span>
			<?php
			if ( $next_link ) {
				echo $next_link;
			}
			?>
			</span>
		</nav>
		<?php if ( $archive_url ) : ?>
		<footer class="site-footer">
			<a href="<?php echo esc_attr( $archive_url ); ?>">Archive</a>
		</footer>
		<?php endif; ?>
	</main>
</body>
</html>
