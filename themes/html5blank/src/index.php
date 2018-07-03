<?php get_header(); ?>

<div class="grid">
	<div class="col-2-3">
		<!-- section -->
		<section>

			<h1><?php _e( 'Latest Posts', 'html5blank' ); ?></h1>

			<?php get_template_part('loop'); ?>

			<?php get_template_part('pagination'); ?>

		</section>
		<!-- /section -->
	</div>
	<div class="col-1-3">
		<?php get_sidebar(); ?>
	</div>
</div>

<?php get_footer(); ?>
