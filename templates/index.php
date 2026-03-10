<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $blog_name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_attr( $style_url ); ?>">
</head>
<body>
	<header class="site-header">
		<a href="#"><?php echo esc_html( $blog_name ); ?></a>
	</header>
	<main>
		<nav class="year-nav">
			<?php foreach ( array_keys( $years ) as $y ) : ?>
			<a href="#year-<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( $years as $year => $posts ) : ?>
		<section class="year-group" id="year-<?php echo esc_attr( $year ); ?>">
			<h2>
				<?php echo esc_html( $year ); ?>
				<span class="year-links">
					<a href="<?php echo esc_attr( $year . '/' . $year_filenames['asc'] ); ?>">oldest first</a>
					<a href="<?php echo esc_attr( $year . '/' . $year_filenames['desc'] ); ?>">newest first</a>
				</span>
			</h2>
			<ul class="post-list">
				<?php foreach ( $posts as $post ) : ?>
				<li>
					<time datetime="<?php echo esc_attr( $post['date_iso'] ); ?>"><?php echo esc_html( $post['date'] ); ?></time>
					<a href="<?php echo esc_attr( $post['href'] ); ?>"><?php echo esc_html( $post['title'] ); ?></a>
					<span class="post-author"><?php echo esc_html( $post['author'] ); ?></span>
				</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php endforeach; ?>
	</main>
</body>
</html>
