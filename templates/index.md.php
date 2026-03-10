# <?php echo $blog_name; ?>

<?php if ( $blog_description ) : ?>
*<?php echo $blog_description; ?>*

<?php endif; ?>
<?php if ( $stats['total'] ) : ?>
<?php echo $stats['total']; ?> posts · <?php echo $stats['date_first']; ?> – <?php echo $stats['date_last']; ?> · <?php echo implode( ', ', $stats['authors'] ); ?>

<?php endif; ?>
<?php if ( ! empty( $pages ) ) : ?>
## Pages

<?php foreach ( $pages as $entry ) : ?>
- [<?php echo $entry['title']; ?>](<?php echo $entry['href']; ?>)
<?php endforeach; ?>

<?php endif; ?>
<?php foreach ( $years as $archive_year => $archive_posts ) : ?>
## <?php echo $archive_year; ?>

<?php foreach ( $archive_posts as $entry ) : ?>
- <?php echo $entry['date']; ?> — [<?php echo $entry['title']; ?>](<?php echo $entry['href']; ?>) (<?php echo $entry['author']; ?>)
<?php endforeach; ?>

<?php endforeach; ?>
