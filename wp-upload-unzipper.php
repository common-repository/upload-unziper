<?php
/*
Plugin Name: Upload Unzipper
Plugin URI: http://wordpress.org/extend/plugins/upload-unziper/
Description: Extracts uploaded zip archives and associates all files with the current post.
Version: 1.0
Author: Ulf Benjaminsson
Author URI: 
*/

/*
pclzip.lib.php is covered by LGPL. wp-upload-unzipper.php (this file) however is GPL.
*/

remove_action('upload_files_upload','wp_upload_tab_upload_action');
add_action( 'upload_files_upload', 'ulfben_uu_upload_tab_upload_action', 1 ); 
 
 
/*
 * @param string filesystem location of file to be unzipped
 * @return array ready to send to ulfben_uu_attach_to_post.
 * This function will unzip a file using the LGPL PclZip library and returns an array containing file information arrays which are ready to send to ulfben_uu_attach_to_post.  
 * Unzipped files are stored in the uploads folder as specified in your Wordpress configuration.
 */		 
function ulfben_uu_unzip($file, $deleteZipWhenDone = true) 
{
	global $mimes;	
	require_once('pclzip.lib.php');
	extract($file); //make everything easier to grab
	
	$archive = new PclZip($file);	
	
	//extracts the archive, calling filterFileNames prior to extracting any file
	$success = $archive->extract(PCLZIP_OPT_PATH, dirname($file).'/', 
								 PCLZIP_CB_PRE_EXTRACT, 'ulfben_uu_filterFileNames' ); 
	
	if ($success == 0)	{ wp_die ("Error : '".$archive->errorInfo(true)."'"); } //full error description.	
		
	$list = $archive->listContent(); //make a local copy of the file list...
	
	for ($i = 0; $i < sizeof($list); $i++) { //...and fix all paths and file names. (Quick & Dirty workaround for issues with PclZip)		
		ulfben_uu_filterFileNames("Q&D", $list[$i]); 
	}		
	
	for ( $i = 0, $n = count($list); $i < $n; $i++ ) { //iterate array of unzipped files and prepare it for attachment. 
		$list[$i]['file'] = str_replace( basename($file), $list[$i]['stored_filename'], $file	); //assign the full physical path								
		$list[$i]['url'] = str_replace( basename($file), $list[$i]['stored_filename'], $url ); 	//assign the full url		
		$list[$i] = array_merge( $list[$i], wp_check_filetype($list[$i]['file'],	$mimes) );	//assign extension and mime type	
	}
		
	if(	$deleteZipWhenDone ){ unlink($file); }		
	
	return $list; //return the fixed file list, not $archive's listContent(). 
}	


/*
ulfben_uu_filterFileNames is called from PclZip prior to actually extracting a file. Thus, giving us an opportunity to sanitize any wierd filenames. 

Problem is these changes are only temporary and not reflected in PclZip's internal file-list - counter-intuitive as it might seem. 
All files are indeed written to disk with clean names, but PclZip still keep references to their "original" names.	

Thus, before returning the filelist from unzip, we call filterFileNames a second time after all extractions are done, 
with $p_event = "Q&D" (Quick & Dirty). This second call is sent a local copy of PclZip's-filelist, alterd it and then 
we can return this fixed list. 

Long story short: PclZip::listContent() is absolutely broken for all our intents and purposes.
*/
function ulfben_uu_filterFileNames($p_event, &$p_header)
{
	if($p_header['folder'] ){ return 0; } //do not extract if it is a folder. (folders are treated like file nodes by PclZip)	
	$path_parts = pathinfo($p_header['filename']);	 		
	$path_parts['basename'] = sanitize_file_name($path_parts['basename']);	//clean the filename (basename is the only data we may alter during pre-extraction)
	
	//we want to make sure any nestled directories within the zip is valid. We can't change p_header['dirname'] during pre-extraction so lets just kill the process.
	$wp_upload_dir = wp_upload_dir(); //get the current directory for uploads
	$subdir = substr_replace( $path_parts['dirname'], "", 0, strlen( $wp_upload_dir['path']."/" ) ); //find wp_upload_dir in the file's path, and remove it. Thus giving us the subdir (to wp_upload_dir) the file will be placed in.
	
	if( !ulfben_uu_is_valid_local_path($subdir) )
	{		
		wp_die( 'The archive contains a badly named directory: <pre>' . $subdir . '</pre>Extraction is aborted, but your archive is kept on the server. You have to delete it manually.');			
		return 0;
	}	
		
	if($path_parts['dirname'] != "."){ 	//when calling for the fixup ("Q&D") dirname will be a simple . We dont need it. 
		$p_header['filename'] = $path_parts['dirname'] . "/" . $path_parts['basename']; 
	} else { 
		$p_header['filename'] = $path_parts['basename']; 
	}		
	
	if( file_exists($p_header['filename'])){ return 0; } //do not extract if the file already exist, or if it is a folder.		
	
	if($p_event == "Q&D"){	$p_header['stored_filename'] = $p_header['filename'];  }	
	return 1;  //go ahead and extract. 			
}	

/*I just lifted this from sanitize_file_name() to create a function that won't throw a fit over path's containing "/" or spaces or other
valid chars for a path. Unfortunatly I don't know regexp at all. I've run it through some shotgun debugging and it seems to work. If you
can improve or confirm this method I would appreciate it. :)*/
function ulfben_uu_is_valid_local_path($path)
{
	$path = strtolower( trim($path) ); //we don't care about case or empty space around the dir name, but we need the comparison to be equal.
	$orig = $path;		
	$path = preg_replace('/&.+?;/', '', $path); // kill entities	
	$path = preg_replace('/[^a-z0-9\s-._\/]/', '', $path);	
	$path = preg_replace('|-+|', '-', $path);	
	//echo "Orig: $orig <br> Valid: $path <br>";
	return ($orig == $path);
}



/*
ulfben_uu_attach_to_post is a refactored part from upload-functions.php -> "wp_upload_tab_upload_action()". 
It's moved into it's own function so we can call it for every file included in a zip-archive.
I've added some checking to make sure we don't add duplicates of any file to the same post.
It returns the id of the attachement, to keep with default behaviour of WP if there's only one file uploaded.
if the attachement is a duplicate it is ignored, and the method returns -1.

	 	WARNING
			In wp_posts (the db table), parent_post defaults to NULL.  
			An ordinary post has no parent, so it's set to 0 appropriately. 
			But an ordinary uploaded file (not attached to any post) should also be set to 0, right? 
			What I find is that they are all set to a value like -1189551062, -1189547712, -1189551992 and so on, wich 
			looks suspiciously like an overflow of some kind. Perhaps a bug? 
			
			Anyway, this is why I test for post_parent <= 0 to find all un-attached files. I hope it'll do what expected but 
			I'm not sure negative parent_post are specified behaviour.		
			
			See bugreport #4962 at http://trac.wordpress.org/ticket/4962 			
*/
function ulfben_uu_attach_to_post($file)
{	
	global $from_tab, $post_id, $style, $post_title, $post_content, $wpdb;	
	if ( !$from_tab ) { $from_tab = 'upload'; }
		
	$url = $file['url'];
	$type = $file['type'];
	$file = $file['file'];
	$filename = basename($file);
	
	//BEGIN ULFBEN
	$safeUrl = $wpdb->escape($url); //safe for db work	(should I escape post_id to?)
	
	if( isset($post_id) && $post_id >= 0 ) { //if we're attaching to a post (parent)				
		$query = "SELECT * FROM wp_posts WHERE post_parent = '$post_id' AND post_type = 'attachment' AND guid = '$safeUrl'";
	} else { //the attachement is parentless (eg, not attached to a post) so lets check the "root"		
		$query = "SELECT * FROM wp_posts WHERE post_parent <= 0 AND post_type = 'attachment' AND guid = '$safeUrl'";
	}
	
	$exists = $wpdb->query($query);	//lets see if this file has been attached before.
	if($exists > 0) {return -1;} //it's a duplicate attachement. Ignore it and return.
	//END ULFBEN	
	
	// Construct the attachment array
	$attachment = array(
		'post_title' => $post_title ? $post_title : $filename,
		'post_content' => $post_content,
		'post_type' => 'attachment',
		'post_parent' => $post_id,
		'post_mime_type' => $type,
		'guid' => $url
	);

	// Save the data
	$id = wp_insert_attachment($attachment, $file, $post_id);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	
	return $id;
}

/*
This method is a copy of upload-functions.php -> "wp_upload_tab_upload_action()" 
My alterations are clearly marked, so it should be relatively straight forward to keep this replacement up to date with new versions of WP core.
*/
function ulfben_uu_upload_tab_upload_action()
{	
	//START ULFBEN
	$unzipArchives = true; //HELP NEEDED: I would really like to have an interface for these options in the upload-iframe but I loath front-end development.
	$deleteZipWhenDone = true; 
	$addZipToPost = false; // If you could add a few checkboxes/radiobuttons for "add zip to post", "unzip and add" and "delete archive when done", I would be much obliged. 
	//END ULFBEN	
	
	global $action;
	if ( isset($_POST['delete']) )
		$action = 'delete';

	switch ( $action ) :
	case 'upload' :
		global $from_tab, $post_id, $style;
		if ( !$from_tab )
			$from_tab = 'upload';

		check_admin_referer( 'inlineuploading' );

		global $post_id, $post_title, $post_content;

		if ( !current_user_can( 'upload_files' ) )
			wp_die( __('You are not allowed to upload files.')
				. " <a href='" . get_option('siteurl') . "/wp-admin/upload.php?style=" . attribute_escape($style . "&amp;tab=browse-all&amp;post_id=$post_id") . "'>"
				. __('Browse Files') . '</a>'
			);			
			
		$overrides = array('action'=>'upload');

		$file = wp_handle_upload($_FILES['image'], $overrides);
		
		if ( isset($file['error']) )
			wp_die($file['error'] . "<br /><a href='" . get_option('siteurl')
			. "/wp-admin/upload.php?style=" . attribute_escape($style . "&amp;tab=$from_tab&amp;post_id=$post_id") . "'>" . __('Back to Image Uploading') . '</a>'
		);
		
		/*			
		* START ULFBEN
		*/							
			if ( $file['type'] == 'application/zip' && $unzipArchives){ //we've just got a zip!								
	  			$files = ulfben_uu_unzip($file, $deleteZipWhenDone); //unzip it... 
	  			if($addZipToPost && !$deleteZipWhenDone) { array_push($files, $file); } //... and attach the zip-file to the post as well.  			  					
			} else {
	    		$files[0] = $file;	//we always want an array to iterate, even if its just one file 	
	  		}  		
	  		
	 		for ( $i = 0, $n = count($files); $i < $n; $i++ ) //iterate through files and attach them to the post
	 		{		 		
	  			if(! is_dir( $files[$i]['file'] ) ) //there can be directories in the zip. we don't want to attach those. 
	  			{			  			
		 			$id = ulfben_uu_attach_to_post($files[$i]); //save id for a proper redirect.
	 			}	 				
		 	}
		 	
			//take us to an overview of all this post's attached files.			
		 	$directTo = "/wp-admin/upload.php?style=$style&tab=browse&action=&ID=&post_id=$post_id&paged"; 
					
		 	if(count($files) == 1 && $id >= 0) { //take us straight to a thumbview of the single uploaded file. (default WP behaviour)	
				$directTo = "/wp-admin/upload.php?style=$style&tab=browse&action=view&ID=$id&post_id=$post_id"; 
			}		
		
		/*			
		* END ULFBEN
		*/	
		
		wp_redirect( get_option('siteurl') . $directTo); 				
		die;					
		break;

	case 'save' :
		global $from_tab, $post_id, $style;
		if ( !$from_tab )
			$from_tab = 'upload';
		check_admin_referer( 'inlineuploading' );

		wp_update_post($_POST);
		wp_redirect( get_option('siteurl') . "/wp-admin/upload.php?style=$style&tab=$from_tab&post_id=$post_id");
		die;
		break;

	case 'delete' :
		global $ID, $post_id, $from_tab, $style;
		if ( !$from_tab )
			$from_tab = 'upload';

		check_admin_referer( 'inlineuploading' );

		if ( !current_user_can('edit_post', (int) $ID) )
			wp_die( __('You are not allowed to delete this attachment.')
				. " <a href='" . get_option('siteurl') . "/wp-admin/upload.php?style=" . attribute_escape($style . "&amp;tab=$from_tab&amp;post_id=$post_id") . "'>"
				. __('Go back') . '</a>'
			);

		wp_delete_attachment($ID);

		wp_redirect( get_option('siteurl') . "/wp-admin/upload.php?style=$style&tab=$from_tab&post_id=$post_id" );
		die;
		break;

	endswitch;
}	

?>
