<?php
/**
 * Zdev_Wcps_Shortcodes class, Handles the Listing shortcodes
 */
class Zdev_Wcps_Shortcodes {
	

	/**
     * function slider, creates slider
	 * @param $atts Array - an associative array of attributes (preferences)
	 * @param $content  String -  the enclosed content
	 * @param $code String -  the shortcode name
	 * @code
	 * @static
     */
	static function slider($atts, $content = null, $code = "") {
		
		global $wpdb,$zdev_wcps_obj; //wpdb var
		static $id =0;
		$id++;

		//attributes
		extract(shortcode_atts(array(
			'prod_ids'		=> '',
			'prod_tags'		=> '',
			'width'		=> '100',
			'height'	=>'100',
			'animation'	=> 'fade',
			'animation_duration' => 600,
			'slide_show' => "true",
			'slide_direction' => 'horizontal',
			'speed'		=> '4000',
			'direction_nav' => "false",
			'pause_play' => "false",
			'animation_loop' => "true",
			'pause_on_action' => "true",
			'pause_on_hover' => "true",
			'limits'	=> '',
			'navigation'=> 'true',
			'cat_ids' =>'',
			'cat_slugs' => '',
			'num_of_prods' => 3,
			'template' => 'default.css',
			'show_price' => "true",
			'show_title' => "true",
			'image_source' => "thumbnail",
			'show_products' => "",
			'background_color' => "",
			'transparent' => "false",
			'price_color' => "",
			'border_color' => "",
			'padding_top' => "",
			'padding_left' => "",
			'padding_bottom' => "",
			'padding-right'	=> ""

		), $atts));

	//get all the output in $out variable
	//enque styles and print scripts
	$out = self::flexslider_scripts($height,$speed,$width,$id,$navigation,$animation,$animation_duration,$slide_show,$slide_direction,$direction_nav,$pause_play,$animation_loop,$pause_on_action,$pause_on_hover);
	wp_print_scripts('jquery_flex');
	wp_enqueue_style( 'flexslider-style' );
	wp_enqueue_style('woocommerce-product-slider-style',plugin_dir_url( __FILE__ ).'templates/'.$template);

	//vertical orientation css
		$vert_css = '';
		if($slide_direction == "vertical") {
			$vert_css = ' psc-vertical';
		}
	//prepare html
	$out .= '<div class="flexslider'.$id. ' flexslider' .$vert_css.'">';
	$out.= '<ul class="slides">';

	//check for show_products
	if($show_products !=''){
		switch($show_products){
			case 'new':
						//get the NEW products
						$loop = new WP_Query(array( 
						 	'post_type' => 'product', 
						 	'stock' => 1, 
						 	'nopaging'=>true,
						 	'orderby' =>'date',
						 	'order' => 'DESC',
							'nopaging'=>true,
						 	));
							break;
			case 'featured':
						//get the FEATURED products
						$loop = new WP_Query(array('post_type' => 'product',
								        'post_status' => 'publish',  
								        'meta_key' => '_featured',  
								        'meta_value' => 'yes',  
								        'nopaging'=>true, 
								));														
						break;
						
			case 'sale':
						//get the SALE products
						$loop = new WP_Query(array(
							 'post_type' => 'product',
							 'nopaging'=>true,
							 'meta_query' => array(
											 'relation' => 'OR',
											 array( // Simple products type
												 'key' => '_sale_price',
												 'value' => 0,
												 'compare' => '>',
												 'type' => 'numeric'),
											 array( // Variable products type
												 'key' => '_min_variation_sale_price',
												 'value' => 0,
												 'compare' => '>',
												 'type' => 'numeric')
										 	)
							));
							break;
		}
	}
	//if categories are selected or entered
	else if($cat_slugs !='') {

		if($cat_slugs!='') {
			//get the products based on the cat slugs
			$loop = new WP_Query(array(	

				'post_type'	=> 'product',
				'nopaging'=>true,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field' => 'slug',
						'terms'=>explode(',',$cat_slugs)
						
					))
			));
		
		}		
		
	}elseif($cat_ids !='') {

		if($cat_ids!='') {
			//get the products based on the cat IDs
			$loop = new WP_Query(array(	

				'post_type'	=> 'product',
				'nopaging'=>true,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field' => 'id',
						'terms'=>explode(',',$cat_ids)
						
					))
			));
		
		}		
		
	} elseif($prod_ids !='') { 

		$prod_arr = array(); //stores the product ids
		
		if($prod_ids !='') {
			$prod_arr = explode(',',$prod_ids);
		}

		$loop = new WP_Query(array('post_type'	=> 'product','post__in'=>$prod_arr,'nopaging'=>true,'orderby'=>'post__in'));

	}elseif($prod_tags !='') { //for product tags

		$prod_arr = array(); //stores the product tags
		
		if($prod_tags !='') {
			$prod_arr = explode(',',$prod_tags);
		}

		$loop = new WP_Query(array(
									'post_type'	=> 'product',
									'nopaging'=>true,
									'tax_query' => array(
                    array(
                        'taxonomy' => 'product_tag',
                        'field' => 'slug',
                        'terms' => $prod_arr,
                        'operator'=> 'IN' //Or 'AND' or 'NOT IN'
                     ))));

	} else { //all products
		$loop = new WP_Query(array('post_type'	=> 'product'));
	}

	

	//determine div width / class based on number of products to display
	$divclass = '';
	switch($num_of_prods) {
		case 1:
				$divclass = "one-slide";
				break;
		case 2:
				$divclass = "two-slides";
				break;
		case 3:
				$divclass = "three-slides";
				break;
		case 4:
				$divclass = "four-slides";
				break;
		case 5:
				$divclass = "five-slides";
				break;
		case 6:
				$divclass = "six-slides";
				break;
	}
	

	//loop through the posts or products
	$index=0;//loop index

	while ($loop->have_posts()) : $loop->the_post(); 
		global $post;


		//initialize or create product object
		$product_obj = get_product($post->ID);

		$price = $title = '';//null value to price and title

	
		//show price
		if($show_price == "true") {	
			$price = $product_obj->get_price_html();			
		}

		//show title
		if($show_title == "true") {
			$title = $post->post_title;
		}

		
		//start list based on number of products
		if($index % $num_of_prods == 0) {$out .='<li>';}
			if($product_obj->product_type == "external"): 
				$prod_url = $product_obj->product_url; 
			else: 
				$prod_url = esc_url( get_permalink(apply_filters("woocommerce_in_cart_product", $product_obj->id)) ); 
			endif;
			$featured_img = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID),$image_source);
			
			//if no featured image for the product, display the placeholder image
			if($featured_img[0] == '' || $featured_img[0] == '/') {
				$featured_img[0] = plugin_dir_url( __FILE__ ).'images/placeholder.png';
			}

			$out.= '<div class="'.$divclass. '"><div class="psc-prod-container"><div class="img-wrap" style="width:'.$width.'%;height:'.$height.'%"><a href="'.$prod_url.'"><img src="'.$featured_img[0].'" alt="'.$title.'" ></a></div><div class="psc-prod-details"><span class="title"><a href="'.$prod_url.'">'.$title.'</a></span><span>'.str_replace(array("<ins>","</ins>","</del>"),array("","","</del><br/>"),$price).'</span></div></div></div>';
		$index++;

		//close list based on number of products
		if($index % $num_of_prods == 0) {$out .='</li>';}
		
	endwhile;
	$out.='</ul></div>';
	$out .='<style>';
	if($background_color!='') {
		$out.= ".flexslider,.flexslider ul.slides{background:$background_color !important;}";
	}else if($transparent == "true") {
		$out.= ".flexslider,.flexslider ul.slides{background-color:transparent !important;}";
	}
	if($title_color!='') {
		$out.= ".flexslider span.title {color:$title_color !important;}";
	}

	if($price_color!='') {
		$out.= ".flexslider span.amount{color:$price_color !important;}";
	}

	if($border_color!='') {
		$out.= ".flexslider{border-color:$border_color !important;}";
	}

	$padding_str='';
	if($padding_top!='') 
		$padding_str .= "padding-top:{$padding_top}px;";
	if($padding_left!='') 
		$padding_str .= "padding-left:{$padding_left}px;";	
	if($padding_right!='') 
		$padding_str .= "padding-right:{$padding_right}px;";	
	if($padding_bottom!='') 
		$padding_str .= "padding-bottom:{$padding_bottom}px;";	

	if($padding_str !='')
		$out .=".flexslider ul.slides li .psc-prod-container { ".$padding_str." }";

	$out.="</style>";
	wp_reset_query();//reset the query
	return $out;
	}
	
	// F L E X   S L I D E R   S C R I P T 
	//--------------------------------------------------------
	static function flexslider_scripts($height,$speed,$width,$id,$direction_nav,$animation,$animation_duration,$slide_show,$slide_direction,$direction_nav,$pause_play,$animation_loop,$pause_on_action,$pause_on_hover) { ?>
		<?php

		return '<script type="text/javascript">
		/* <![CDATA[ */
			jQuery(document).ready(function() {
				jQuery(window).load(function() {
					jQuery(".flexslider'.$id.'").flexslider({
				 animation: "'.$animation.'",
				  controlsContainer: ".flex-container",
				  slideshow: '.$slide_show.',//Boolean: Animate slider automatically
				  slideDirection: "'.$slide_direction.'",
				  slideshowSpeed: '.$speed.',				//Integer: Set the speed of the slideshow cycling, in milliseconds
				  animationDuration: '.$animation_duration.',			//Integer: Set the speed of animations, in milliseconds
				  directionNav: '.$direction_nav.',				//Boolean: Create navigation for previous/next navigation? (true/false)
				  controlNav: false,					//Boolean: Create navigation for paging control of each clide? Note: Leave true for	
				  pausePlay: '.$pause_play.',
				  animationLoop: '.$animation_loop.',
				  pauseOnAction: '.$pause_on_action.',
				  pauseOnHover: '.$pause_on_hover.',				  
				  mousewheel: false,					//Boolean: Allow slider navigating via mousewheel				  
				  start: function(slider) {
					jQuery(".total-slides").text(slider.count);
				  },
				  after: function(slider) {
					jQuery(".current-slide").text(slider.currentSlide);
				  }
						
						
					});
				});
			});
		/* ]]> */
		</script>';
	}
}
add_shortcode("woo_product_slider", array("Zdev_Wcps_Shortcodes", "slider"));
?>
