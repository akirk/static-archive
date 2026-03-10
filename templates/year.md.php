# <?php echo $year; ?>

<?php foreach ( $entries as $entry ) : ?>
## <?php echo $entry['title']; ?>

*<?php echo $entry['date']; ?> — <?php echo $entry['author']; ?>*

<?php echo $entry['content_md']; ?>

---

<?php endforeach; ?>
