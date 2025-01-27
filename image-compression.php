<?php
/*
Plugin Name: Image Compressor & WebP Generator
Description: Optimize your website’s performance with seamless image compression and WebP generation. Automatically compress all uploaded images and generate WebP versions to deliver a faster, more efficient browsing experience. Enhance your site’s speed and SEO effortlessly!
Version: 1.0
Author: Ryan Codizal
*/

// Prevent direct access to the file
if ( !defined('ABSPATH') ) {
    exit;
}

/*================================================================*
                      Custom functions
=================================================================*/
// function for generating webp to default image
function compress_image($file_status, $file_type, $image_path) {
  $file_path = ($file_status == 'uploads') ? $image_path : get_attached_file($image_path) ;
  if ($file_type == 'png') {
    $image = imagecreatefrompng($file_path);
    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);
  } else {
    $image = imagecreatefromjpeg($file_path);
  }
  imagewebp($image, str_replace(".".$file_type."" ,".".$file_type.".webp", $file_path), 85);
  imagedestroy($image);
}

// function to generate webp to responsive images
function compress_responsive_image($file_type, $url) {
  $responsive_args = array('thumbnail', 'medium', 'medium_large', 'large');
  foreach ($responsive_args as $size) {
    if (image_get_intermediate_size($url, $size) && file_exists(wp_get_upload_dir($url)['basedir'].'/'.image_get_intermediate_size($url,$size)['path'])) {
      compress_image('uploads', $file_type, wp_get_upload_dir($url)['basedir'].'/'.image_get_intermediate_size($url,$size)['path']);
    }
  }
}
/*================================================================*
            Function handler by uploading image
=================================================================*/
add_action('admin_init', 'admin_image_compression', 20, 1);
function admin_image_compression(){
  global $pagenow;
  if ( $pagenow == 'upload.php' || $pagenow == 'media-new.php' ) {
    $image_argument = array(
      'orderby'     => 'date',
      'order'       => 'DESC',
      'post_type'   => 'attachment',
      'date_query'  => [
        'after'     => '5 hours ago',
        'inclusive' => true,
      ],
      'post_status' => array(
        'publish',
        'inherit',
        'any',
      ),
    );
    $image_query = new WP_Query($image_argument);
    if ( $image_query->have_posts() ) {
      while ( $image_query->have_posts() ) {
        $image_query->the_post();
        $file_type = substr(get_attached_file(get_the_ID()),strrpos(get_attached_file(get_the_ID()),'.') +1 );
        if (file_exists(get_attached_file(get_the_ID())) && ($file_type == 'jpg' || $file_type == 'jpeg' || $file_type == 'png')) {
          compress_image('uploading', $file_type, get_the_ID());
          compress_responsive_image($file_type, get_the_ID());
        }
      }
    }
  }
}
/*================================================================*
              Delete all related files of image
=================================================================*/
add_filter( 'wp_delete_file', 'delete_webp' );
function delete_webp($file) {
  if (file_exists(str_replace(".png" ,".png.webp", $file))) {
    @unlink(str_replace(".png" ,".png.webp", $file));
  }
  if (file_exists(str_replace(".jpg" ,".jpg.webp", $file)) || file_exists(str_replace(".jpeg" ,".jpeg.webp", $file))) {
    @unlink(str_replace(".jpg" ,".jpg.webp", $file));
  }
  if (file_exists(str_replace(".jpeg" ,".jpeg.webp", $file)) || file_exists(str_replace(".jpg" ,".jpg.webp", $file))) {
    @unlink(str_replace(".jpeg" ,".jpeg.webp", $file));
  }
  return $file;
}
/*================================================================*
          Function to create new column in media list
=================================================================*/
// Function to add new column to media page
add_filter( 'manage_media_columns', 'column_id' );
function column_id($columns) {
  $columns['compression'] = __('Image Compression');
  return $columns;
}

// Function to add data on the column of image compression
add_filter( 'manage_media_custom_column', 'column_compression_row', 10, 2 );
function column_compression_row($column_name, $column_id) {
  if ($column_name == 'compression') {
    // Variables
    $file_type = substr(get_attached_file($column_id),strrpos(get_attached_file($column_id),'.') +1 );
    $compress_message = '<p>File type cannot be compressed!</p>';
    $file_responsive_path = (!empty(image_get_intermediate_size($column_id, 'thumbnail')['path'])) ? str_replace(".".$file_type."" ,".".$file_type.".webp", wp_get_upload_dir($column_id)['basedir'].'/'.image_get_intermediate_size($column_id, 'thumbnail')['path'] ) : '';
    $thumbnail_path = (!empty(image_get_intermediate_size($column_id, 'thumbnail')['path'])) ? wp_get_upload_dir($column_id)['basedir'].'/'.image_get_intermediate_size($column_id, 'thumbnail')['path'] : '';

    // To add webp to existing image by clicking the 'Activate the image compressor.'
    if (isset($_GET['compress']) && $_GET['compress'] == $column_id && file_exists(get_attached_file($column_id))) {
      compress_image('existing', $file_type, $_GET['compress']);
      compress_responsive_image($file_type, $_GET['compress']);
    }

    // Text to be displayed on Image Compression column.
    if ($file_type == 'webp' || file_exists( str_replace( ".".$file_type."" ,".".$file_type.".webp", get_attached_file($column_id) ) ) && (file_exists($file_responsive_path) || empty($thumbnail_path)) ) {
      $compress_message = '<b>This image is already compressed!</b><br/><br/>';
    } else {
      $compress_message = '<a href="'.add_query_arg('compress', $column_id).'">Activate the image compressor.</a><br/><br/>';
    }
    if ($file_type != 'jpg' && $file_type != 'jpeg' && $file_type != 'png' && $file_type != 'webp') {
      $compress_message = '<p>File type cannot be compressed!</p>';
    }
    if (!file_exists( get_attached_file($column_id) )) {
      $compress_message = '<p>The image does not appear in the database!</p>';
    }
    // Result of text
    echo $compress_message;

    // List down the result of compression and responsive images
    if (file_exists(get_attached_file($column_id)) && $file_type == 'jpg' || $file_type == 'jpeg' || $file_type == 'png') {
      $image_original = round(filesize(get_attached_file($column_id)) / 1024 , 2);
      $image_original_webp = file_exists(str_replace(".".$file_type."" ,".".$file_type.".webp", get_attached_file($column_id))) ? round(filesize(str_replace(".".$file_type."" ,".".$file_type.".webp", get_attached_file($column_id))) / 1024  , 2) : 0;
      echo "<b>Original Size</b> - ".$image_original."kb | ";
      echo "<b>WEBP Size</b> - ".$image_original_webp."kb</br>";

      $responsive_args = array('thumbnail' => 'Thumbnail', 'medium' => 'Medium', 'medium_large' => 'Medium Large', 'large' => 'Large');
      foreach ($responsive_args as $key => $value) {
        if (image_get_intermediate_size($column_id, $key) && file_exists(wp_get_upload_dir($column_id)['basedir'].'/'.image_get_intermediate_size($column_id,$key)['path'])) {
          $file_path = wp_get_upload_dir($column_id)['basedir'].'/'.image_get_intermediate_size($column_id,$key)['path'];
          $file_size = round(filesize($file_path) / 1024 , 2);
          $image_responsive_webp = file_exists(str_replace(".".$file_type."" ,".".$file_type.".webp", $file_path)) ? round(filesize(str_replace(".".$file_type."" ,".".$file_type.".webp", $file_path)) / 1024 , 2) : 0;
          echo "<b>".$value."</b> - ".$file_size."kb | ";
          echo "<b>WEBP Size</b> - ".$image_responsive_webp."kb</br>";
        }
      }
    }

  }
}
/*================================================================*
            Function to generate webp by bulk action
=================================================================*/
// Function to add new data to bulk action filter
add_filter( 'bulk_actions-upload', 'bulk_actions_image_compress' );
function bulk_actions_image_compress( $bulk_array ) {
	$bulk_array[ 'tmjp_image_compress' ] = 'Generate WEBP';
	return $bulk_array;
}

// Function to process the created action
add_filter( 'handle_bulk_actions-upload', 'bulk_actions_image_handler', 10, 3 );
function bulk_actions_image_handler( $redirect, $doaction, $object_ids ) {
	// To works only in compress image
	if ( 'tmjp_image_compress' === $doaction ) {

    foreach ($object_ids as $image_id) {
      $file_type = substr(get_attached_file($image_id),strrpos(get_attached_file($image_id),'.') +1 );
      // To generate webp to existing image
      if (($file_type == 'jpg' || $file_type == 'jpeg' || $file_type == 'png') && file_exists(get_attached_file($image_id)) && !file_exists(  str_replace(".".$file_type."" ,".".$file_type.".webp", get_attached_file($image_id)))) {
        compress_image('existing', $file_type, $image_id);
        compress_responsive_image($file_type, $image_id);
      }
    }

		// To add new parameter on URL for adding notice in page
		$redirect = add_query_arg(
			'tmjp_image_compress', // just a parameter for URL
			count( $object_ids ), // how many posts have been selected
			$redirect
		);
	}

	return $redirect;
}

// Add message for success bulk action.
add_action( 'admin_notices', 'bulk_actions_image_notices' );
function bulk_actions_image_notices() {
	if( !empty( $_REQUEST[ 'tmjp_image_compress' ] ) ) { ?>
		<div class="updated notice is-dismissible">
			<p>Image successfully compressed!</p>
		</div>
<?php }
}
