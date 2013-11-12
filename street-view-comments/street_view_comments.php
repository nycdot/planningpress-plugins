<?php
/*
Plugin Name: Street View Comments
Plugin URI:
Description: Allows for comments to be associated with Google Street View images along a street.
Version: 0.4
Author: Chris Abraham and Neil Freeman
*/

// enqueue scripts. called in the shortcode handler (svc_show_mapper)
function fitzgerald_scripts($localization) {
  // javascript
  wp_enqueue_script( 'gmaps', 'http://maps.googleapis.com/maps/api/js?sensor=false' );
  wp_enqueue_script( 'jquery-ui-custom', plugins_url( 'js/lib/jquery-ui-1.8.21.custom.min.js' , __FILE__ ), array('jquery'), '1.8.21' );
  wp_enqueue_script( 'jquery-sparkline', plugins_url( 'js/lib/jquery.sparkline-2.0.min.js' , __FILE__ ), array('jquery'). '2.0' );
  wp_enqueue_script( 'json2.a', plugins_url( 'js/lib/json2.min.js' , __FILE__ ));
  wp_enqueue_script( 'underscore.1.3.3', plugins_url( 'js/lib/underscore-1.3.3.min.js' , __FILE__ ), array(), '1.3.3' );
  // doesn't work with newer backbone
  wp_enqueue_script( 'backbone.0.9.2', plugins_url( 'js/lib/backbone-0.9.2.min.js' , __FILE__ ), array('underscore.1.3.3'), '0.9.2' );
  wp_enqueue_script( 'fitzgerald-routes', plugins_url( 'js/routes.js' , __FILE__ ), array('gmaps', 'backbone.0.9.2'), '0.4' );
  wp_enqueue_script( 'fitzgerald-views', plugins_url( 'js/views.js', __FILE__ ), array('gmaps', 'backbone.0.9.2'), '0.4' );

  // this one right here needs to be localized!
  wp_enqueue_script( 'fitzgerald-display', plugins_url( 'js/display.js', __FILE__ ), array('fitzgerald-views'), '0.4' );
  wp_localize_script( 'fitzgerald-display', 'settings', $localization); //pass any php settings to javascript
  
  // ie conditional
  if (preg_match('/(?i)msie [1-8]/',$_SERVER['HTTP_USER_AGENT'])) {
    wp_enqueue_script('html5shiv','http://html5shiv.googlecode.com/svn/trunk/html5.js');
  }

  // css
  wp_enqueue_style( 'jquery-ui-custom-css', plugins_url( 'js/lib/jquery-ui-1.8.21.custom.css' , __FILE__ ) );
  wp_enqueue_style( 'fitzgerald-css', plugins_url( 'css/style.css' , __FILE__ ) );
  wp_enqueue_style( 'swanky-fonts', 'http://fonts.googleapis.com/css?family=Swanky+and+Moo+Moo|Shadows+Into+Light|Loved+by+the+King' );

}

// THE AJAX ADD ACTIONS
add_action( 'wp_ajax_intersections', 'svc_intersections' );
add_action( 'wp_ajax_nopriv_intersections', 'svc_intersections' ); // need this to serve non logged in users
add_action( 'wp_ajax_feedback', 'svc_feedback' );
add_action( 'wp_ajax_nopriv_feedback', 'svc_feedback' ); // need this to serve non logged in users

function svc_feedback() {
  $request_body = file_get_contents('php://input');
  if ($request_body) {
    $request = json_decode($request_body);
    svc_post_comment($request->intersection_id, $request->heading, $request->pitch, $request->zoom, $request->desc);
  }
}

function svc_post_comment($post_id, $heading, $pitch, $zoom, $desc) {
  $time = current_time('mysql');

  $data = array(
    'comment_post_ID' => $post_id,
    'comment_content' => $desc,
    'comment_date' => $time,
    'comment_approved' => '1'
  );

  $comment_id = wp_new_comment($data);

  add_comment_meta($comment_id, 'pitch', $pitch, true);
  add_comment_meta($comment_id, 'heading', $heading, true);
  add_comment_meta($comment_id, 'zoom', $zoom, true);
  echo $comment_id;
  exit;

}

function svc_intersections(){
  global $post;
  header( "Content-Type: application/json" );

  $query_args = array(
    'post_type' => 'svc_intersection',
    'svc_intersection_tags' => (isset($_GET['tag'])) ? $_GET['tag'] : null,
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'asc'
  );
  query_posts($query_args);

  $i = array();
  if ( have_posts() ) : while ( have_posts() ) : the_post();
    $j = array();
    $j['lat'] = $lat = (double) get_post_meta($post->ID, 'lat', true);
    $j['lng'] = $lng = (double) get_post_meta($post->ID, 'lng', true);
    $j['name'] = get_the_title();
    $j['id'] = $post->ID;
    $args = array(
      'status' => 'approve',
      'post_id' => $post->ID
    );
    $comments = get_comments($args);
    $comments_out = array();
    foreach($comments as $c) {
      $k = array();
      $k['id'] = (int) $c->comment_ID;
      $k['lat'] = $lat;
      $k['lng'] = $lng;
      $k['heading'] =  (double) get_comment_meta($c->comment_ID, 'heading', true);
      $k['pitch'] =  (double) get_comment_meta($c->comment_ID, 'pitch', true);
      $k['zoom'] = (int) get_comment_meta($c->comment_ID, 'zoom', true);
      $k['desc'] = $c->comment_content;
      $comments_out[] = $k;
    }

    $j['feedback'] = $comments_out;

    $i[] = $j;
  endwhile; endif;

  $response = json_encode($i);
  echo $response;
  exit;// wordpress may print out a spurious zero without this - can be particularly bad if using json
}

function svc_show_mapper($atts){

  extract( shortcode_atts( array(
      'background' => false,
      'mainstreet' => 'Fourth Avenue',
      'tag' => ''
      ), $atts ) );

  // include markup
  $template = file_get_contents( __DIR__ . '/fitzgerald_markup.html' );
  $css = ($background) ? ' style="background-image:url('. $background .')"' : '' ;

  $out = sprintf( $template, $css );

  // localize fitzgerald scripts
  $localization = array(
    'mainstreet' => $mainstreet,
    'backbone_url' => get_bloginfo('url') . "/wp-admin/admin-ajax.php?action=intersections&tag=" . $tag,
    'feedback_url' =>  get_bloginfo('url') . "/wp-admin/admin-ajax.php?action=feedback"
  );

  fitzgerald_scripts($localization);

  return $out;
}

add_shortcode("street-view-comments", "svc_show_mapper");

add_action( 'init', 'svc_create_post_type' );

function svc_create_post_type() {
  register_post_type( 'svc_intersection',
                    array(
                          'labels' => array(
                                            'name' => __( 'Intersections' ),
                                            'singular_name' => __( 'Intersection' ),
                                            'add_new_item' => __( 'Add New Intersection' ),
                                            'edit_item' => __( 'Edit Intersection' ),
                                            'search_items' => __( 'Search Intersections' ),
                                            'not_found' =>  __('No intersections found'),
                                           ),
                          'public' => true,
                          'exclude_from_search' => true,
                          'has_archive' => true,
                          'supports' => array('title', 'comments', 'page-attributes', 'author'),
                          'rewrite' => array('slug' => 'intersections')
                         )
                    );

  $labels = array(
    'name' => _x( 'Tags', 'taxonomy general name' ),
    'singular_name' => _x( 'Tag', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Tags' ),
    'popular_items' => __( 'Popular Tags' ),
    'all_items' => __( 'All Tags' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Writer' ),
    'update_item' => __( 'Update Tag' ),
    'add_new_item' => __( 'Add New Tag' ),
    'new_item_name' => __( 'New Tag Name' ),
    'separate_items_with_commas' => __( 'Separate tags with commas' ),
    'add_or_remove_items' => __( 'Add or remove tags' ),
    'choose_from_most_used' => __( 'Choose from the most used tags' ),
    'menu_name' => __( 'Tags' ),
  );


  register_taxonomy(
    'svc_intersection_tags',
    'svc_intersection',
    array('labels'=>$labels)
    );

}

function svc_intersection_custom_columns($column) {
    global $post;
    $custom = get_post_custom();
    switch ($column){
    case "svc_col_order":
      echo $post->menu_order;
      break;
    }
}
add_action ("manage_posts_custom_column", "svc_intersection_custom_columns");

function svc_intersection_edit_columns($columns) {
    $extracolumns = array_merge($columns,
                                array("svc_col_order" => "Order"));
    return $extracolumns;
}
add_filter ("manage_edit-svc_intersection_columns", "svc_intersection_edit_columns");

add_filter( 'manage_edit-svc_intersection_sortable_columns', 'svc_intersection_sortable_column' );
function svc_intersection_sortable_column( $columns ) {
    $columns['svc_col_order'] = 'menu_order';
    return $columns;
}


/* Define the custom box */
add_action( 'add_meta_boxes', 'svc_add_custom_box' );

/* Do something with the data entered */
add_action( 'save_post', 'svc_save_postdata' );

/* Adds a box to the main column on the Post and Page edit screens */
function svc_add_custom_box() {
    add_meta_box(
        'svc_location',
        __( 'Location', 'svc_textdomain' ),
        'svc_inner_custom_box',
        'svc_intersection',
        'side'
    );
/*    add_meta_box(
        'svc_street_map',
        __( 'Street Map', 'svc_textdomain' ),
        'svc_inner_custom_box_sm',
        'svc_intersection',
        'side'
    );
*/
}

function svc_inner_custom_box( $post ) {
  $lat = get_post_meta($post->ID, 'lat', true);
  $lng = get_post_meta($post->ID, 'lng', true);
  // Use nonce for verification
  wp_nonce_field( plugin_basename( __FILE__ ), 'svc_noncename' );

  // The actual fields for data entry
  echo '<label for="svc_lat">';
       _e("Lat", 'svc_textdomain' );
  echo '</label> ';
  echo '<input type="text" id="svc_lat" name="svc_lat" value="' . $lat . '" size="15" />';
  echo '<br/><label for="svc_lng">';
       _e("Lng", 'svc_textdomain' );
  echo '</label> ';
  echo '<input type="text" id="svc_lng" name="svc_lng" value="' . $lng . '" size="15" />';
}

function svc_inner_custom_box_sm( $post ) {
  $show = get_post_meta($post->ID, 'show', true);
  if ($show)
    $show = 'checked="checked"';
  else
    $show = '';
  $label = get_post_meta($post->ID, 'label', true);
  if ($label)
    $label = 'checked="checked"';
  else
    $label = '';
  // Use nonce for verification
  wp_nonce_field( plugin_basename( __FILE__ ), 'svc_noncename' );

  // The actual fields for data entry
  echo '<label for="svc_show">';
       _e("Show cross-street", 'svc_textdomain' );
  echo '</label> ';
  echo '<input type="checkbox" id="svc_show" name="svc_show" ' . $show . ' size="15" />';
  echo '<br/><label for="svc_label">';
       _e("Label cross-street", 'svc_textdomain' );
  echo '</label> ';
  echo '<input type="checkbox" id="svc_label" name="svc_label" ' . $label . ' size="15" />';
}

/* When the post is saved, saves our custom data */
function svc_save_postdata( $post_id ) {
  // verify if this is an auto save routine.
  // If it is our form has not been submitted, so we dont want to do anything
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
      return;

  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times

  if ( !wp_verify_nonce( $_POST['svc_noncename'], plugin_basename( __FILE__ ) ) )
      return;


  // Check permissions
  if ( !current_user_can( 'edit_post', $post_id ) )
        return;

  // OK, we're authenticated: we need to find and save the data

  $mydata = $_POST['svc_lat'];
  update_post_meta($post_id, 'lat', $mydata);
  $mydata = $_POST['svc_lng'];
  update_post_meta($post_id, 'lng', $mydata);
  $mydata = $_POST['svc_show'];
  update_post_meta($post_id, 'show', $mydata);
  $mydata = $_POST['svc_label'];
  update_post_meta($post_id, 'label', $mydata);
}

register_activation_hook( __FILE__, 'svc_activate' );

function svc_activate() {
  query_posts('post_type=svc_intersection');
  if(!have_posts()) {
    include 'setup_intersections.php';
  }
}

function svc_unused_meta_boxes() {
  remove_meta_box('commentstatusdiv','svc_intersection','normal'); // Comment Status
}

add_action('admin_head', 'svc_unused_meta_boxes');

?>
