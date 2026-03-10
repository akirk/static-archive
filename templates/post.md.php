---
title: <?php echo $post_title ? $post_title : '(untitled)'; ?>

date: <?php echo $post_date_iso; ?>

author: <?php echo $post_author; ?>

---

<?php if ( $post_title ) : ?>
# <?php echo $post_title; ?>

<?php endif; ?>
*<?php echo $post_date; ?> — <?php echo $post_author; ?>*

<?php echo $content_md; ?>
