<?php get_header(); ?>
<div class="grid">
	<div class="col-1">
		<div role="banner">
			<div class="flexslider">
				<ul class="slides">
				<?php
				$args = array( 'post_type' => 'banner');
				$loop = new WP_Query( $args );
				while ( $loop->have_posts() ) : $loop->the_post();
				  the_title();
				  echo '<li>';
				  the_content();
				  echo '</li>'; 
				endwhile; ?>
			  </ul>
			</div>
		</div>
		<!-- section -->
		
		<br>
		<?php if (have_posts()): while (have_posts()) : the_post(); ?>
		<div class="grid">
			<div class="col-3-4">
				<section>
				<!-- article -->
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

					<?php the_content(); ?>

					<br class="clear">

					<?php edit_post_link(); ?>

				</article>
				<!-- /article -->

		<?php endwhile; ?>

		<?php else: ?>
			<!-- article -->
			<article>

				<h2><?php _e( 'Sorry, nothing to display.', 'html5blank' ); ?></h2>

			</article>
			<!-- /article -->

		<?php endif; ?>

				</section>
			</div>
			<div class="col-1-4">
				<section>
					<?php if(!function_exists('dynamic_sidebar') || !dynamic_sidebar('front-page')) ?>
				</section>
			</div>
		</div>

		
		<!-- /section -->
	</div>
</div>
<?php get_footer(); ?>
