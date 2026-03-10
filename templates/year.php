<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $year ); ?> &mdash; <?php echo esc_html( $blog_name ); ?></title>
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
		<h1>
			<?php echo esc_html( $year ); ?>
			<span class="year-order-toggle">
				<?php if ( 'asc' === $order ) : ?>
				oldest first &middot; <a href="<?php echo esc_attr( $other_url ); ?>">newest first</a>
				<?php else : ?>
				<a href="<?php echo esc_attr( $other_url ); ?>">oldest first</a> &middot; newest first
				<?php endif; ?>
			</span>
		</h1>
		<?php foreach ( $entries as $entry ) : ?>
		<article id="<?php echo esc_attr( basename( $entry['href'], '.html' ) ); ?>">
			<?php if ( $entry['title'] ) : ?>
			<h2><a href="<?php echo esc_attr( $entry['href'] ); ?>"><?php echo esc_html( $entry['title'] ); ?></a></h2>
			<?php endif; ?>
			<div class="post-meta">
				<time datetime="<?php echo esc_attr( $entry['date_iso'] ); ?>"><?php echo esc_html( $entry['date'] ); ?></time>
				<span class="post-author"><?php echo esc_html( $entry['author'] ); ?></span>
			</div>
			<?php echo $entry['content']; ?>
		</article>
		<hr>
		<?php endforeach; ?>
	</main>
</body>
</html>
