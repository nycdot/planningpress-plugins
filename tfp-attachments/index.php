<?php

 /**
  * Plugin Name: TFP Attachments
  * Plugin URI:  
  * Description: Attachments gives the ability to append any number of Media Library items to Pages, Posts, and Custom Post Types
  * Author:      Jonathan Christopher / rewritten for DOT by Neil Freeman
  * Author URI:  
  * Version:     1.0
  * Text Domain: attachments
  * Domain Path: /languages/
  * License:     GPLv2 or later
  * License URI: http://www.gnu.org/licenses/gpl-2.0.html
  */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

// Store whether or not we're in the admin
if( !defined( 'IS_ADMIN' ) ) define( 'IS_ADMIN',  is_admin() );

// Environment check
$wp_version = get_bloginfo( 'version' );

if( !version_compare( PHP_VERSION, '5.2', '>=' ) && IS_ADMIN && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) )
{
    // failed PHP requirement
    require_once ABSPATH . '/wp-admin/includes/plugin.php';
    deactivate_plugins( __FILE__ );
    wp_die( esc_attr( __( 'Attachments requires PHP 5.2+. Attachments has been automatically deactivated.' ) ) );
}
else
{
    define( 'ATTACHMENTS_DIR', plugin_dir_path( __FILE__ ) );
    define( 'ATTACHMENTS_URL', plugin_dir_url( __FILE__ ) );

    // load current version of Attachments
    require_once 'classes/class.attachments.php';
}

/*
* 
* Add our lovely attachments instance to the page
*/
class tfp_attachments {

  public static function register( $attachments ) {
    $fields = array(
      array(
        'name'      => 'caption',                   // unique field name
        'type'      => 'text',                      // registered field type
        'label'     => __( 'Caption [text in brackets] will be in a link. No brackets means entire text is linked.', 'attachments' ),  // label to display
        'default'   => 'Download the presentation (pdf)',  // default value upon selection
      ),

      array(
        'name'      => 'url',
        'type'      => 'text',
        'label'     => __( 'URL', 'attachments' ),
        'default'   => 'http://nyc.gov/dot/downloads/pdf/',
      ),

      array(
        'name'      => 'date',
        'type'      => 'date',
        'label'     => __( 'Date', 'attachments' ),
        'default'   => '',
      ),

      array(
        'name'      => 'note',
        'type'      => 'textarea',
        'label'     => __( 'Note (internal)', 'attachments' ),
      ),

    );

    $args = array(

      // title of the meta box (string)
      'label'         => 'Links and attachments',

      // all post types to utilize (string|array)
      'post_type'     => array( 'post', 'page' ),

      // meta box position (string) (normal, side or advanced)
      'position'      => 'normal',

      // meta box priority (string) (high, default, low, core)
      'priority'      => 'high',

      // allowed file type(s) (array) (image|video|text|audio|application)
      'filetype'      => null,  // no filetype limit

      // include a note within the meta box (string)
      'note'          => 'Add links to files here. Save with the blue Save/Update button above.',

      // by default new Attachments will be appended to the list
      // but you can have then prepend if you set this to false
      'append'        => true,

      // text for 'Attach' button in meta box (string)
      'button_text'   => __( 'New Linked File', 'attachments' ),

      // text for modal 'Attach' button (string)
      'modal_text'    => __( 'New Linked File', 'attachments' ),

      // which tab should be the default in the modal (string) (browse|upload)
      'router'        => 'browse',

      // fields array
      'fields'        => $fields,

    );

    $attachments->register( 'tfp_attachments_instance', $args ); // unique instance name
  }

  public static function write_attachments( $id ) {
    $attachments = new Attachments( 'tfp_attachments_instance' );
    // write the attachments for a specific post
    if( $attachments->exist() ) :
      print '<p class="arr-p">';
      while( $attachment = $attachments->get() ) :
        $attachment->fields->date = ($attachment->fields->date) ? self::format_date( $attachment->fields->date ) : '' ;

        print self::parse_caption($attachment->fields) ;
      endwhile;
      print "</p>\n";
    endif;
  }

  private static function format_date($date, $format='(F j, Y)') {
    return date( $format, strtotime( $date ) );
  }


  private static function parse_caption( $fields ) {
    $pattern = '<span class="arr">%s<a href="%s">%s</a>%s</span>'."\n" ;

    $l_b = strpos( $fields->caption, '[' ) ;
    $r_b = strpos( $fields->caption, ']' ) ;

    if ($l_b === false || $r_b === false ) :
      return sprintf(
        $pattern,
        '',
        $fields->url,
        $fields->caption,
        ' ' . $fields->date
      ) ;

    else:
      $before = substr( $fields->caption, 0, $l_b ) ;
      $link_text = substr( $fields->caption, $l_b + 1, $r_b - $l_b - 1 ) ;
      $after = substr( $fields->caption, $r_b + 1, -1 ) ;

      return sprintf(
        $pattern,
        $before,
        $fields->url,
        $link_text,
        ' '. $after .' '. $fields->date
      ) ;

    endif;
  }

} 
add_action( 'attachments_register', array( 'tfp_attachments', 'register' ) );
add_action( 'tfp_after_post_content', array( 'tfp_attachments', 'write_attachments' ), 20, 1 );
add_action( 'tfp_after_single_content', array('tfp_attachments', 'write_attachments' ), 20, 1 );


?>