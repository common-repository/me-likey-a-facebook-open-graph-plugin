<?php
/*
Plugin Name: Me Likey
Plugin URI: http://www.jqueryin.com/projects/me-likey-wordpress-plugin
Description: Plugin for adding Facebook's Open Graph API support to your page. Allows user's to display a "Like" button on individual blog posts and pages.
Version: 1.0.5
Author: Corey Ballou
Author URI: http://www.jqueryin.com
*/

/* Copyright (C) 2010 Corey Ballou */

class Me_Likey {

   private $version = '1.0.3';

   function __construct()
   {
	  // add an admin options menu
	  add_action('admin_menu', array(&$this, 'admin_menu'));

	  // define custom boxes on posts and pages
	  add_action('admin_menu', array(&$this, 'add_custom_box'));

	  // handle saving of page and post options
	  add_action('save_post', array(&$this, 'save_post_data'));

	  // add namespaces
	  add_filter('language_attributes', array(&$this, 'add_namespaces'));

	  // add headers
	  add_action('wp_head', array(&$this, 'add_headers'));

	  // add shortcode handler for displaying
	  add_shortcode('like_button', array(&$this, 'shortcode_handler'));

	  // add filter to add to content
	  add_filter('the_content', array(&$this, 'insert_me_likey') , 99);
   }

   /**
    * Admin menu entry.
    *
    * @access	public
    */
   public function admin_menu()
   {
	  if (function_exists('add_options_page')) {
		 $id = add_options_page('Me Likey Options', 'Me Likey Options', 10, basename(__FILE__), array(&$this, 'admin_options'));
		 add_action('admin_print_scripts-'.$id, array(&$this, 'add_admin_js'));
	  }
   }

   /**
    * Add jQuery preview handler to admin options page.
    *
    * @access	public
    */
   public function add_admin_js()
   {
	  wp_enqueue_script('me-likey-admin',
						plugins_url('me-likey-admin.js', __FILE__),
						array('jquery'));
   }

   /**
    * Add namespaces to the HTML tag.
    */
   public function add_namespaces($attrs)
   {
	  return trim($attrs) .
	  "\n xmlns:og=\"http://opengraphprotocol.org/schema/\"";
	  "\n xmlns:fb=\"http://www.facebook.com/2008/fbml\"";
   }

   /**
    * Display open graph meta tags for the given blog post.
    *
    * @access	public
    */
   public function add_headers()
   {
	  global $post;

	  if (is_feed()) return;

	  if (is_object($post)) $post_id = $post->ID;
	  else $post_id = $post;

	  // get options containing admin ids
	  $options = get_option('me_likey');

	  // get the default url
	  $link = get_bloginfo('url');

	  // get the default title
	  $title = get_bloginfo('name');

	  // get the default description
	  $description = get_bloginfo('description');

	  // get the default site name
	  $site_name = $title;

	  // default type to blog
	  $type = 'blog';

	  // default image
	  if (!empty($options['image']) && strpos($options['image'], 'http') === 0) {
		 $image = $options['image'];
	  }

	  // only applicable to single posts or pages
	  if (is_single() || is_page()) {

		 // check for an excerpt
		 $excerpt = get_the_excerpt();
		 if (strlen($excerpt) < 10) {
			$excerpt = get_the_content();
		 }

		 $excerpt = strip_tags($excerpt);
		 if (strlen($excerpt) > 10)
			$description = stripslashes(substr($excerpt, 0, 255));

		 // grab the title
		 $title = trim(stripslashes(wp_title('', false)));

		 // grab the post url
		 $link = get_permalink($post_id);

		 // grab the post photo (if available)
		 $args = array(
			'post_type' 		=> 'attachment',
			'post_mime_type' 	=> 'image',
			'numberposts' 		=> -1,
			'order' 			=> 'ASC',
			'post_status' 		=> null,
			'post_parent' 		=> $post_id
		 );

		 // set the site name
		 if (!empty($options['site_name'])) {
			$site_name = $options['site_name'];
		 }

		 // check for type
		 if (!empty($options['type'])) {
			 $type = $options['type'];
		 }

		 // check for a thumbnail attachment
		 $attachments = get_posts($args);
		 if (!empty($attachments)) {
			foreach ($attachments as $attachment) {
			   $image = wp_get_attachment_thumb_url($attachment->ID);
			   break;
			}
		 }

	  }

	  // update some headers
	  $headers = '';

	  // check for administrators
	  if (!empty($options['admins'])) {
		 $headers .= sprintf("<meta property=\"fb:admins\" content=\"%s\" />\n", $options['admins']);
	  } else if (!empty($options['app_id'])) {
		 $headers .= sprintf("<meta property=\"fb:app_id\" content=\"%s\" />\n", $options['app_id']);
	  }

	  $headers .= sprintf("<meta property=\"og:title\" content=\"%s\" />\n", stripslashes($title));
	  $headers .= sprintf("<meta property=\"og:description\" content=\"%s\" />\n", stripslashes($description));
	  $headers .= sprintf("<meta property=\"og:type\" content=\"%s\" />\n", $type);
	  $headers .= sprintf("<meta property=\"og:url\" content=\"%s\" />\n", $link);
	  $headers .= sprintf("<meta property=\"og:site_name\" content=\"%s\" />\n", stripslashes($site_name));

	  // check for image
	  if (!empty($image)) {
		 $headers .= sprintf("<meta property=\"og:image\" content=\"%s\" />\n", $image);
	  }

	  echo $headers;
   }

   /**
    * Display the Facebook Like button within the
    * page or post.
    *
    * @access	public
    * @param	string	$html
    */
   public function insert_me_likey($html)
   {
	  global $post;

	  // get user defined options
	  $options = get_option('me_likey');

	  // check if the button is enabled
	  $enable_me_likey = get_post_meta($post->ID, 'enable_me_likey_button', true);
	  if (empty($enable_me_likey) || $enable_me_likey == '1') {
		// add to content
		$html = $this->build_me_likey_button($html, $options['position']);
	}
      return $html;
   }

   /**
    * Display the shortcode.
    */
   public function me_likey_shortcode($attr, $content)
   {
	  return me_likey_button(false);
   }

    /**
     * Add a custom section to post and page screens for whether
     * to enable the Like button.
     *
     * @access	public
     */
   public function add_custom_box()
   {
      add_meta_box('me_likey_enable_button', __( 'Me Likey "Like" Button', 'me_likey'),
                    array(&$this, 'display_custom_box'), 'post', 'side');
      add_meta_box('me_likey_enable_button', __( 'Me Likey "Like" Button', 'me_likey'),
                    array(&$this, 'display_custom_box'), 'page', 'side');
   }

   /**
    * Display the inner fields on the page and post admin areas.
    *
    * @access	public
    */
   public function display_custom_box()
   {
	  global $post;
	  if (is_object($post)) $post_id = $post->ID;
      else $post_id = $post;

      $option_value = '';

      if ($post_id > 0) {
         $enable_me_likey = get_post_meta($post_id, 'enable_me_likey_button', true);
         if (!empty($enable_me_likey)) {
            $option_value = $enable_me_likey;
         }
      }

	  // sse nonce for verification
?>

      <input type="hidden" name="me_likey_noncename" id="me_likey_noncename" value="<?php echo wp_create_nonce(plugin_basename(__FILE__) ); ?>" />
      <p>
		 <label>
			<input type="radio" name="me_likey_button" value ="1" <?php checked('1', $option_value); ?> />
			<?php _e('Enabled', 'me_likey'); ?>
		 </label>
		 <label>
			<input type="radio" name="me_likey_button" value ="0"  <?php checked('0', $option_value); ?> />
			<?php _e('Disabled', 'me_likey'); ?>
		 </label>
      </p>

<?php
   }

   /**
    * Save post settings for whether to enable or disable open graph.
    *
    * @access	public
    */
   public function save_post_data($post_id)
   {
	  // do some verification
	  if (!wp_verify_nonce($_POST['me_likey_noncename'], plugin_basename(__FILE__))) {
		 return $post_id;
      }

	  // ensure user's have proper privileges
      if ('page' == $_POST['post_type']) {
         if (!current_user_can('edit_page', $post_id))
            return $post_id;
      } else {
         if (!current_user_can('edit_post', $post_id ))
            return $post_id;
      }

	  // save data
	  if (isset($_POST['me_likey_button'])) {
		 $enabled = ($_POST['me_likey_button'] == '1') ? '1' : '0';
		 update_post_meta($post_id, 'enable_me_likey_button', $enabled);
	  }
   }

   /**
    * Options page.
    *
    * @access	public
    */
   public function admin_options()
   {
	  // default option values
	  $defaultOptionVals = array(
		 'type'			=> 'website',		// type of site
		 'admins'		=> '',			// comma separated list of admin uids
		 'app_id'		=> '',			// facebook app id
		 'site_name'		=> '',			// name of site (no www or .com)
		 'height'		=> '24',		// the height of the iframe
		 'width'		=> '150',		// the width of the iframe
		 'verb'			=> 'like',		// 'like' or 'recommend'
		 'font'			=> 'arial',		// arial, lucida grande, segoe ui, tahoma, trebuchet ms, verdana
		 'color_scheme'		=> 'light',		// 'light' or 'dark',
		 'position'		=> 'manual',		// before, after, both, manual
		 'class'		=> 'me-likey',		// default container class name
		 'layout'		=> 'button_count',	// 'standard' or 'button_count',
		 'image'		=> '',			// default image URL
		 'show_faces'		=> false		// whether to show liked faces
	  );

	  // get all options
	  $options = get_option('me_likey');
	  if (!empty($options)) {
		 foreach ($options as $k => $v) {
			$defaultOptionVals[$k] = $v;
		 }
	  }

	  // arrays for testing and html form
	  $types = array('website', 'blog');
	  $verbs = array('like', 'recommend');
	  $fonts = array('arial', 'lucida grande', 'segoe ui', 'tahoma', 'trebuchet ms', 'verdana');
	  $colors = array('light', 'dark');
	  $positions = array('before', 'after', 'both', 'manual');
	  $layouts = array('standard', 'button_count');

	  // watch for submission
	  if (!empty($_POST)) {

		 // validate referrer
		 check_admin_referer('me_likey_valid');

		 if (empty($_POST['me_likey_admins']) && empty($_POST['me_likey_appid'])) {
			echo '<div id="message" class="updated fade"><p><strong>' . __('You must provide either admin ids or an app id.') . '</strong></p></div>';
			return false;
		 } else {
			if (!empty($_POST['me_likey_admins'])) {
			   $admins = $_POST['me_likey_admins'];
			   if (strpos($admins, ',') === false) {
				  $admins = str_replace(' ', ', ', $_POST['me_likey_admins']);
			   }
			   $defaultOptionVals['admins'] = $admins;
			}

			if (!empty($_POST['me_likey_appid'])) {
			   $defaultOptionVals['app_id'] = $_POST['me_likey_appid'];
			}
		 }

		 if (!empty($_POST['me_likey_type'])) {
			$type = $_POST['me_likey_type'];
			if (in_array($type, $types)) {
			   $defaultOptionVals['type'] = $type;
			}
		 }

		 if (!empty($_POST['me_likey_sitename'])) {
			$defaultOptionVals['site_name'] = htmlentities($_POST['me_likey_sitename']);
		 }

		 if (!empty($_POST['me_likey_image'])) {
			$defaultOptionVals['image'] = htmlentities($_POST['me_likey_image']);
		 }

		 if (!empty($_POST['me_likey_width'])) {
			$width = str_replace('px', '', $_POST['me_likey_width']);
			if (is_numeric($width)) {
			   $defaultOptionVals['width'] = $width;
			}
		 }

		 if (!empty($_POST['me_likey_height'])) {
			$height = str_replace('px', '', $_POST['me_likey_height']);
			if (is_numeric($height)) {
			   $defaultOptionVals['height'] = $height;
			}
		 }

		 if (!empty($_POST['me_likey_verb']) && in_array($_POST['me_likey_verb'], $verbs)) {
			$defaultOptionVals['verb'] = $_POST['me_likey_verb'];
		 }

		 if (!empty($_POST['me_likey_font'])) {
			$font = strtolower($_POST['me_likey_font']);
			if (in_array($font, $fonts)) {
			   $defaultOptionVals['font'] = $font;
			}
		 }

		 if (!empty($_POST['me_likey_color'])) {
			if (in_array($_POST['me_likey_color'], $colors)) {
			   $defaultOptionVals['color_scheme'] = $_POST['me_likey_color'];
			}
		 }

		 if (!empty($_POST['me_likey_position'])) {
			if (in_array($_POST['me_likey_position'], $positions)) {
			   $defaultOptionVals['position'] = $_POST['me_likey_position'];
			}
		 }

		 if (!empty($_POST['me_likey_class'])) {
			$defaultOptionVals['class'] = $_POST['me_likey_class'];
		 }

		 if (!empty($_POST['me_likey_layout'])) {
			if (in_array($_POST['me_likey_layout'], $layouts)) {
			   $defaultOptionVals['layout'] = $_POST['me_likey_layout'];
			}
		 }

		 if (isset($_POST['me_likey_faces'])) {
			$defaultOptionVals['show_faces'] = ($_POST['me_likey_faces'] == '1') ? true : false;
		 }

		 // update options
		 update_option('me_likey', $defaultOptionVals);

		 // show success
		 echo '<div id="message" class="updated fade"><p><strong>' . __('Your settings have been saved.') . '</strong></p></div>';

	  }

	  // display the admin page
?>

	  <div style="width: 620px; padding: 10px">
		 <h2><?php _e('Me Likey Options'); ?></h2>
		 <p>
			<small>Brought to you by <a href="http://www.jqueryin.com">JQueryin' - A Web Development Blog</a>.</small>
		 </p>
		 <p>
			Me Likey has a number of configuration options. Details regarding the configuration items can be found on the
			<a href="http://developers.facebook.com/docs/opengraph" target="_blank">Facebook Open Graph Protocol Page</a> as well as
			the <a href="http://developers.facebook.com/docs/reference/plugins/like" target="_blank">Facebook Like Button Reference Page</a>.
			Me Likey utilizes Open Graph META tags as this improves the overall site handling for all cases of individuals "liking" your site
			(i.e. from an external source).
		 </p>
		 <p>The only manual work we <em>highly recommend</em> you do is ensure your current theme's <strong>header.php</strong>
			file is up to date with the <code>language_attributes()</code> function included, i.e.:
		 </p>
		 <p>
			<code><?php echo htmlentities('<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>'); ?></code>
		 </p>
		 <form action="" method="post" id="me_likey_form" accept-charset="utf-8" style="position:relative">
			 <?php wp_nonce_field('me_likey_valid'); ?>
			 <input type="hidden" name="action" value="update" />
			 <input type="hidden" name="page_options" value="me_likey_type" />
			 <table class="form-table">
				 <tr valign="top">
					 <th scope="row">Admins*</th>
					 <td>
						<input name="me_likey_admins" id="me_likey_admins" value="<?php echo htmlentities($defaultOptionVals['admins']); ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						Takes a comma separated list of Facebook user-ids. To find your admin id, simply go to your profile page on Facebook and hover over your default profile image. Your user id should be at the end in your browsers status bar.
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">App ID*</th>
					 <td>
						<input name="me_likey_appid" id="me_likey_appid" value="<?php echo htmlentities($defaultOptionVals['app_id']); ?>" disabled="disabled" readonly="readonly" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						The App ID administrative handling has temporarily been disabled as an option until we can pinpoint a bug. Please use the <em>Admin ID</em>.
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Site Name</th>
					 <td>
						<input name="me_likey_sitename" id="me_likey_sitename" value="<?php echo stripslashes(htmlentities($defaultOptionVals['site_name'])); ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Default Image URL</th>
					 <td>
						<input name="me_likey_image" id="me_likey_image" value="<?php echo $defaultOptionVals['image']; ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						A full URL path to an image you would like to be used as a default image on Facebook when somebody "likes" your post. The image must be a <strong>minimum of 50x50</strong> pixels in size and must not exceed a width to height ratio of 3x1.
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Type Of Site</th>
					 <td>
						<select name="me_likey_type" id="me_likey_type">
						   <?php foreach ($types as $type): ?>
						   <option value="<?php echo $type; ?>"<?php echo ($defaultOptionVals['type'] == $type) ? ' selected="selected"' : ''; ?>><?php echo $type; ?></option>
						   <?php endforeach; ?>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Layout Style</th>
					 <td>
						<select name="me_likey_layout" id="me_likey_layout">
						   <?php foreach ($layouts as $layout): ?>
						   <option value="<?php echo $layout; ?>"<?php echo ($defaultOptionVals['layout'] == $layout) ? ' selected="selected"' : ''; ?>><?php echo $layout; ?></option>
						   <?php endforeach; ?>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Container Class Name</th>
					 <td>
						<input name="me_likey_class" id="me_likey_class" value="<?php echo htmlentities($defaultOptionVals['class']); ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Container Height</th>
					 <td>
						<input name="me_likey_height" id="me_likey_height" value="<?php echo htmlentities($defaultOptionVals['height']); ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Container Width</th>
					 <td>
						<input name="me_likey_width" id="me_likey_width" value="<?php echo htmlentities($defaultOptionVals['width']); ?>" />
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Verb <small>(for display)</small></th>
					 <td>
						<select name="me_likey_verb" id="me_likey_verb">
						   <?php foreach ($verbs as $verb): ?>
						   <option value="<?php echo $verb; ?>"<?php echo ($defaultOptionVals['verb'] == $verb) ? ' selected="selected"' : ''; ?>><?php echo $verb; ?></option>
						   <?php endforeach; ?>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Font Family</th>
					 <td>
						<select name="me_likey_font" id="me_likey_font">
						   <?php foreach ($fonts as $font): ?>
						   <option value="<?php echo $font; ?>"<?php echo ($defaultOptionVals['font'] == $type) ? ' selected="selected"' : ''; ?>><?php echo $font; ?></option>
						   <?php endforeach; ?>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Color Scheme</th>
					 <td>
						<select name="me_likey_color" id="me_likey_color">
						   <?php foreach ($colors as $color): ?>
						   <option value="<?php echo $color; ?>"<?php echo ($defaultOptionVals['color_scheme'] == $color) ? ' selected="selected"' : ''; ?>><?php echo $color; ?></option>
						   <?php endforeach; ?>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Show Faces?</th>
					 <td>
						<select name="me_likey_faces" id="me_likey_faces">
						   <option value="0"<?php echo ($defaultOptionVals['show_faces'] == false) ? ' selected="selected"' : ''; ?>>No</option>
						   <option value="1"<?php echo ($defaultOptionVals['show_faces'] == true) ? ' selected="selected"' : ''; ?>>Yes</option>
						</select>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						Show Faces only works if you have the layout style set to standard. You also need to ensure you set the height and width of
						your IFRAME accordingly to accomodate for the images and extra text.
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">Display Position</th>
					 <td>
						<select name="me_likey_position" id="me_likey_position">
						   <?php foreach ($positions as $position): ?>
						   <option value="<?php echo $position; ?>"<?php echo ($defaultOptionVals['position'] == $position) ? ' selected="selected"' : ''; ?>><?php echo $position; ?></option>
						   <?php endforeach; ?>
						</select>
						<p>
						   By default, the position is set to <em>manual</em> which allows you to manually place the button in your theme using the tag
						<code>me_likey_button()</code> within the_loop or use the shortcode <code>[like_button]</code> in your post body.
						</p>
						<p>
						   Even when this option is set to <em>before, after, or both</em>, you can still use the custom tag or shortcode to include
						   Me Likey multiple times on your pages.
						</p>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						<button type="button" id="me-likey-preview" class="button-primary" value="Preview">Preview</button>
						<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
					 </td>
				 </tr>
				 <tr valign="top">
					 <th scope="row">&nbsp;</th>
					 <td>
						<div id="me-likey-preview-window" style="display: none; width: 400px; height: 200px; padding: 10px; border: 1px solid #ccc; overflow-x: hidden; overflow-y: auto;">
						   <span style="color: #ccc">preview window</span>
						</div>
					 </td>
				 </tr>
			 </table>

		 </form>
	  </div>

<?php
       /**
	* Short code handler for the button.
	*
	* @param mixed $attr
	* @param string $content
	*/
    	function shortcode_handler($attr, $content = null) {
        	return me_likey_button(false);
	}

   }

   /**
    * Places the button on the page depending on the position
    * selected by the user.
    *
    * @access	private
    * @param	string	$content
    * @param	string	$position
    * @return	string
    */
   private function build_me_likey_button($content, $position)
   {
	  $button = me_likey_button(false);
	  if ($position == 'before') {
		 $content = $button . $content;
	  } else if ($position == 'after') {
		 $content .= $button;
	  } else if ($position == 'both') {
		 $content = $button . $content . $button;
	  } else {
		 // assume manual position, do nothing
	  }
	  return $content;
   }

}

/**
 * Template function to add the like button.
 */
function me_likey_button($display = true) {
   global $wp_query;
   $post = (isset($wp_query->post)) ? $wp_query->post : false;
   if (is_object($post)) $post_id = $post->ID;
   else $post_id = $post;

   // initialize output
   $output = '';

   // get options
   $options = get_option('me_likey');

   $show_faces = ($options['show_faces']) ? 'true' : 'false';

   // determine if in the loop
   if (!empty($post_id) && in_the_loop()) {
	  // determine if enabled
	  $enabled = get_post_meta($post_id, 'enable_me_likey_button', true);
	  if ($enabled == '' || $enabled == '1') {
		 // create the output
		 $output = '<iframe class="' . $options['class'] . '" src="http://www.facebook.com/plugins/like.php?href=' . urlencode(get_permalink($post_id));
		 $output .= '&amp;layout=' . $options['layout'] . '&amp;show_faces=' . $show_faces . '&amp;width=' . $options['width'] . '&amp;height=' . $options['height'];
		 $output .= '&amp;action=' . $options['verb'] . '&amp;font=' . $options['font'];
		 $output .= '&amp;colorscheme=' . $options['color_scheme'] . '" ';
		 $output .= 'scrolling="no" frameborder="0" allowTransparency="true" style="border:none; overflow:hidden; width:' . $options['width'] . 'px; height:' . $options['height'] . 'px"></iframe>';
	  }
   } else {
	  // get the link
	  $link = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? 'https://' : 'http://';
	  $link .= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	  // create the output
	  $output = '<iframe class="' . $options['class'] . '" src="http://www.facebook.com/plugins/like.php?href=' . urlencode($link);
	  $output .= '&amp;layout=' . $options['layout'] . '&amp;show_faces=' . $show_faces . '&amp;width=' . $options['width'] . '&amp;height=' . $options['height'];
	  $output .= '&amp;action=' . $options['verb'] . '&amp;font=' . $options['font'];
	  $output .= '&amp;colorscheme=' . $options['color_scheme'] . '" ';
	  $output .= 'scrolling="no" frameborder="0" allowTransparency="true" style="border:none; overflow:hidden; width:' . $options['width'] . 'px; height:' . $options['height'] . 'px"></iframe>';
   }

   if ($display) echo $output;
   return $output;
}

// enable plugin on init
add_action('init', 'OhhhPenMe');

function OhhhPenMe() {
   $me_likey = new Me_Likey();
}
?>
