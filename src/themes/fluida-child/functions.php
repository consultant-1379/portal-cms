<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_parent_css' ) ):
    function chld_thm_cfg_parent_css() {
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array( 'fluida-themefonts' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10 );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_separate', trailingslashit( get_stylesheet_directory_uri() ) . 'ctc-style.css', array( 'chld_thm_cfg_parent','fluida-main','fluida-responsive' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css' );

//Removes Home from the Forum breadcrumb
function mycustom_breadcrumb_options() {
    // Home - default = true
    $args['include_home']    = false;
    return $args;
}
add_filter('bbp_before_get_breadcrumb_parse_args', 'mycustom_breadcrumb_options');

//Add Meta-Box to New Page editors
add_filter( 'rwmb_meta_boxes', 'your_prefix_meta_boxes' );
function your_prefix_meta_boxes( $meta_boxes ) {
    $meta_boxes[] = array(
        'title'      => __( 'Help Section', 'textdomain' ),
        'post_types' => 'page',
        'fields'     => array(
            array(
                'id'   => 'custom_html',
                'type' => 'custom_html',
                'std' => '
			<h2>Coming Soon</h2>
			<br>
    			<p>
				Will include some of the following:
			      	<ul>
        				<li>Information on editing this page</li>
        				<li>Helpful tips</li>
        				<li>Contact details if you require more assistance</li>
      				</ul>
    			</p>'
            ),
        ),
    );
    return $meta_boxes;
}

// END ENQUEUE PARENT ACTION


/**
 * Hook to add the portal menu settings into the wp admin menu
 * @author Cein
 */
add_action('admin_menu', 'adpPortalMenuSettings');
function adpPortalMenuSettings() {
    add_menu_page( 
		'ADP Portal Menu Settings', 
		'Portal Menu', 
		'edit_theme_options', 
		'adp_portal_menu_settings', 
		'adpPortalMenuPage', 
		'', 
		24
	);
}

/**
 * Adds the portal menu setting page to wordpress admin area, this includes saving the settings to this page
 * @author Cein
 */
function adpPortalMenuPage() {
	if (isset($_POST['adp_portal_json_object'])) {
        $value = $_POST['adp_portal_json_object'];
        update_option('adp_portal_json_object', $value);
    }

	$value = get_option('adp_portal_json_object', '');
	
	?>
		<form method="POST">
			<br/><h1>ADP Portal Menu JSON Object</h1><br/>
			<?php wp_editor( stripslashes($value), 'adp_portal_json_object_editor',array('textarea_name'=>'adp_portal_json_object')) ?>
			<br/><br/>
			<input type="submit" value="Save" class="button button-primary button-large">
		</form>
	<?php
}

/**
 * Menu fetch endpoint 
 * @param {obj} contains the adp portal menu json object
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/adp_portal_menu', array(
        'methods'  => 'GET',
        'callback' => function () {
            return stripslashes( get_option('adp_portal_json_object', ''));
        },
    ) );
} );



/**
 * Menu builder fetch endpoint
 * @param {obj} containing the adp portal menu
 * @return {arr} array of menu items
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/fetch_menu/(?P<menuSlug>\w+)', array(
        'methods'  => 'GET',
        'callback' => function ( WP_REST_Request $request ) {
			$menuSlug = stripslashes( $request['menuSlug'] );
			$currentMenu = [];
			if( trim( $menuSlug ) !== '' ) {
                $currentMenu = wp_get_nav_menu_items( $menuSlug );

				if( count( $currentMenu ) > 0 ) {
					$currentMenu = updateTheReturnedMenuData($currentMenu);
                }
                
			}
            return $currentMenu;
        },
    ) );
} );

/**
 * updates point for menu fetches
 * @param {arr} $currentMenu  the array of the fetched menu
 * @return {arr} updated menu data associated
 * @author Cein
 */
function updateTheReturnedMenuData( $currentMenu ) {
    // check if the menu item is a category, if so fetch the associated time if it exists
    foreach( $currentMenu as $menuItemIndex => $menuItemObject ){
        // set category time placeholder and if category has a linked menu
        $currentMenu[$menuItemIndex]->timeToComplete = '';
        $currentMenu[$menuItemIndex]->linkedMenuFirstPageSlug = '';

        if( isset( $menuItemObject->object ) &&  $menuItemObject->object === 'category' ) {
            $categoryMetaData = get_option( 'category_'.$menuItemObject->object_id );

            // time to complete
            if( isset( $categoryMetaData['timeToComplete'] ) ) {
                $currentMenu[$menuItemIndex]->timeToComplete = $categoryMetaData['timeToComplete'];
            }
            
            // category first page slug for the side menu
            if( isset( $categoryMetaData['article_side_menu_slug'] ) ) {
                $sideMenuSlug = $categoryMetaData['article_side_menu_slug'];
                $fetchedMenuArray = fetchMenusBySlug( [$sideMenuSlug] );
                if( count( $fetchedMenuArray ) > 0 && count( $fetchedMenuArray[0] ) > 0 && isset($fetchedMenuArray[0][0]->url) ) {
                    $url = $fetchedMenuArray[0][0]->url;
                    $currentMenu[$menuItemIndex]->linkedMenuFirstPageSlug = getSlugFromUrl( $url );
                }
            }
        }
    }
    return $currentMenu;
}

/**
 * Gets the slug from a url
 * @param {str} $url the url to strip the slug from the end of
 * @return {str} the slug from the url
 * @author Cein
 */
function getSlugFromUrl( $url ) {
    $urlArray = explode('/', $url );
    $urlLength = count( $urlArray ) - 1;
    
    if( $url !== '' ) {
        if( $urlArray[ $urlLength ] !== '' ) {
            return $urlArray[ $urlLength ];
        } else if( $urlArray[ ( $urlLength -1 ) ] !== '' )  {
            return $urlArray[ ( $urlLength -1 ) ];
        }
    }
    return '';
}



/**
 * Fetches and builds a menu by the given menu slug
 * @param {str} $menuSlug the slug of the menu to fetch
 * @returns {}
 * @author Cein
 */
function fetchMenuBySlug( $menuSlug ) {
    $currentMenu = [];
    if( trim( $menuSlug ) !== '' ) {
        $currentMenu = wp_get_nav_menu_items( $menuSlug );
        if( count( $currentMenu ) > 0 ) {
            // check if the menu item is a category, if so fetch the associated time if it exists
            foreach( $currentMenu as $menuItemIndex => $menuItemObject ){
                // set category time placeholder
                $currentMenu[$menuItemIndex]->timeToComplete = '';
                if( isset( $menuItemObject->object ) &&  $menuItemObject->object === 'category' ) {
                    $categoryMetaData = get_option( 'category_'.$menuItemObject->object_id );
                    if( isset( $categoryMetaData['timeToComplete'] ) ) {
                        $currentMenu[$menuItemIndex]->timeToComplete = $categoryMetaData['timeToComplete'];
                    }
                }
            }
        }
    }
    return $currentMenu;
}

/**
 * Endpoint to fetch preview data
 * @return {arr} - post_title {str}  title of the preview
 * 		 - post_content {str} content of the preview
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/preview/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => function ($data) {
		global $wpdb;
		$postId =  (int)$data['id'];
		$pageObject = [];

		if( $postId > 0 ){
			// fetch the preview data
			$queryToFetchPreviewData = '
				SELECT 
					post_title,
					post_content
				FROM wp_posts
				WHERE
					ID = '.$postId.'
				ORDER BY post_modified DESC
				LIMIT 1
			';
			$pageObject = $wpdb->get_results( $queryToFetchPreviewData , 'OBJECT' );
		}
		return $pageObject;
       }
    ) );
} );

/**
 * Post/page Preview redirect hook
 * @author Cein
 */
function custom_preview_page_link($link) {
	global $post;
	$serverUrl = $_SERVER['HTTP_HOST'];
	$serverUrl = str_replace( ':23309' , ':58090', $serverUrl );
  	return "https://$serverUrl/preview/$post->ID";
	// return 'https://seliius18473.seli.gic.ericsson.se:58090/preview/'.$post->ID;
}
add_filter('preview_post_link', 'custom_preview_page_link');


/***************************************************************************************************************************/
/** Script to redirect failed login to our custom login page with error message **/
function pippin_login_fail( $username ) {
    $referrer = $_SERVER['HTTP_REFERER']; 

    if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') ) {
        wp_redirect( site_url() . '/?login=failed' );  
        exit;
    }
}

add_action( 'wp_login_failed', 'pippin_login_fail' ); 
/***************************************************************************************************************************/



/***************************************************************************************************************************/
/** Prevent the WP page/post editor from inserting <p> and <br> tags on new line or line-breaks **/
add_filter( 'the_content', 'wpautop' );
add_filter( 'the_excerpt', 'wpautop' );
/***************************************************************************************************************************/


/*creates a new post section for the Tutorial pages posts
 * @author Cein
 */
function createTutorialsMetaPosts() {

	$supports = array(
		'title', // post title
		'editor', // post content
		'revisions', // post revisions
	);
	$labels = array(
		'name' => _x('Tutorial pages', 'plural'),
		'singular_name' => _x('Tutorial page', 'singular'),
		'menu_name' => _x('Tutorial Pages', 'admin menu'),
		'name_admin_bar' => _x('Tutorial pages', 'admin bar'),
		'add_new' => _x('Add New', 'add new'),
		'add_new_item' => __('Add New Tutorial Page'),
		'new_item' => __('New tutorial page'),
		'edit_item' => __('Edit tutorial page'),
		'view_item' => __('View tutorial page'),
		'all_items' => __('All tutorial pages'),
		'search_items' => __('Search tutorial pages'),
		'not_found' => __('No tutorial pages found.'),
	);
	$args = array(
		'show_in_rest' => true,
		'supports' => $supports,
		'labels' => $labels,
		'public' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'tutorials'),
		'has_archive' => true,
		'hierarchical' => false,
		'show_in_nav_menus' => true,
		'taxonomies' => [ 'category']
	);


	register_post_type( 'tutorials', $args );
}
add_action( 'init', 'createTutorialsMetaPosts' );

/**
* End point to get a tutorial by id 
*@author Cein
*/
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/tutorialPageById/(?P<tutorialPageId>\w+)', array(
        'methods'  => 'GET',
        'callback' => function ( WP_REST_Request $request ) {
			global $wpdb;
			$tutorialPageId = stripslashes( $request['tutorialPageId'] );
			if( trim( $tutorialPageId ) !== '' ) {
				global $wpdb;
				return $wpdb->get_results( "SELECT   wp_posts.* FROM wp_posts  WHERE 1=1  AND wp_posts.ID = $tutorialPageId AND wp_posts.post_type = 'tutorials'  ORDER BY wp_posts.post_date DESC" );
			}
            return [];
        },
    ) );
} );

/**
* End point to get a tutorial by slug
*@author Cein
*/
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/tutorialPageBySlug', array(
        'methods'  => 'GET',
        'callback' => function () {
			global $wpdb;
			$tutorialPageSlug =  trim( stripslashes( $_GET['slug'] ) );
			if( trim( $tutorialPageSlug ) !== '' ) {
				return $wpdb->get_results( "SELECT 
								wp_posts.* 
							    FROM wp_posts  
							    WHERE 
								wp_posts.post_name = '$tutorialPageSlug' AND 
								wp_posts.post_type = 'tutorials'
								ORDER BY wp_posts.post_date DESC" );
			}
            return [];
        },
    ) );
} );


/**
* Add categories and tags to pages
* @author Cein
*/
function myplugin_settings() {  
    // Add tag metabox to page
    register_taxonomy_for_object_type('post_tag', 'page'); 
    // Add category metabox to page
    register_taxonomy_for_object_type('category', 'page');  
}
 // Add to the admin_init hook of your theme functions.php file 
add_action( 'init', 'myplugin_settings' );


/**
 * End pont to get all categories and their children
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/allcategories', array(
        'methods'  => 'GET',
        'callback' => function ( WP_REST_Request $request ) {
			return ["hide_empty" => 0,
			"type"      => "post",      
			"orderby"   => "name",
			"order"     => "ASC" ];
        },
    ) );
} );


//add average time to category create & edit Hooks
add_action ( 'edit_category_form_fields', 'addAdditionalCategoryFields');
add_action ( 'category_add_form_fields', 'addAdditionalCategoryFields');

/**
 * Adds extra fields the category section
 * @author Cein
 * @param
 */
function addAdditionalCategoryFields( $categoryDataObj ) {
    $categoryMetaDataArr = get_option( "category_$categoryDataObj->term_id");
    $selectedSideMenuSlug = ( isset($categoryMetaDataArr['article_side_menu_slug'] ) ? $categoryMetaDataArr['article_side_menu_slug'] : '' );
    $selectOptionsHtml = buildSideMenuSelectOptionHtml( $selectedSideMenuSlug );
	?>
	<tr class="form-field">
		<td valign="top"><label for="timeToComplete">Avg Time To Complete</label></td>
		<td>
			<input type="text" name="Cat_meta[timeToComplete]" id="Cat_meta[timeToComplete]" style="width:100%;" value="<?= ( isset($categoryMetaDataArr['timeToComplete']) ? $categoryMetaDataArr['timeToComplete'] : '') ?>"><br />
			<span class="description">The average length of time to complete this category. Please indicate the unit of measure with the time. e.g 20 min</span>
		</td>
	</tr>
	<tr class="form-field">
		<td colspan="2"><br/><br/></td>
	</tr>

    <tr class="form-field">
		<td valign="top"><label for="article_side_menu_slug">Side Menu Link</label></td>
		<td>
            <select id="article_side_menu_slug" name="Cat_meta[article_side_menu_slug]" style="width:100%">
                <?php echo $selectOptionsHtml; ?>
            </select>
			<span class="description">Link a side menu to this category</span>
		</td>
	</tr>
	<tr class="form-field">
		<td colspan="2"><br/><br/></td>
	</tr>
	<?php
	
}

// save extra category extra fields hook
add_action ( 'edited_category', 'saveAdditionalCategoryFields');
add_action ( 'create_category', 'saveAdditionalCategoryFields');
/**
 * Saves the additional field value within the category area
 * @param {int} id of the worked category
 * @author Cein
 */
function saveAdditionalCategoryFields( $categoryId ) {
    if ( isset( $_POST['Cat_meta'] ) ) {
        $categoryMetaData = get_option( 'category_'.$categoryId);
        foreach ( array_keys($_POST['Cat_meta']) as $key){
            if (isset($_POST['Cat_meta'][$key])){
                $categoryMetaData[$key] = $_POST['Cat_meta'][$key];
            }
        }
        //save the option array
        update_option( 'category_'.$categoryId, $categoryMetaData );
    }
}

// ROLES

/**
 * Added in the new tutorial_editor role
 * @author Cein
 */
if( empty( get_role('tutorial_editor') ) ){
	add_role( 'tutorial_editor', 'Tutorial Editor', 
	[ 
		'delete_others_posts' => true,
		'delete_posts' => true,
		'delete_published_posts' => true,
		'edit_others_posts' => true,
		'edit_posts' => true,
		'edit_published_posts' => true,
		'manage_categories' => true,
		'manage_links'  => true,
		'publish_posts'  => true,
		'read'  => true,
		'read_private_posts'  => false,
		'unfiltered_html'  => true,
		'upload_files'  => true
	] );
}

/**
 * Added in the new tutorial_manager role
 * @author Cein
 */
if( empty( get_role('tutorial_manager') ) ){
	add_role( 'tutorial_manager', 'Tutorial Manager', 
	[ 
		'delete_others_posts' => true,
		'delete_posts' => true,
		'delete_published_posts' => true,
		'edit_others_posts' => true,
		'edit_posts' => true,
		'edit_published_posts' => true,
		'manage_categories' => true,
		'manage_links'  => true,
		'publish_posts'  => true,
		'read'  => true,
		'read_private_posts'  => false,
		'unfiltered_html'  => true,
		'upload_files'  => true,
		'edit_theme_options' => true
	] );
}

/**
 * New tutorial_manager role
 * @author Cein
 */
if( empty( get_role('editor_manager') ) ){
	add_role( 'editor_manager', 'Editor Manager', 
	[ 
		'delete_others_pages'  => true,
		'delete_others_posts'  => true,
		'delete_pages'  => true,
		'delete_posts'  => true,
		'delete_private_pages'  => true,
		'delete_private_posts'  => true,
		'delete_published_pages'  => true,
		'delete_published_posts'  => true,
		'edit_others_pages'  => true,
		'edit_others_posts'  => true,
		'edit_pages'  => true,
		'edit_posts'  => true,
		'edit_private_pages'  => true,
		'edit_private_posts'  => true,
		'edit_published_pages'  => true,
		'edit_published_posts'  => true,
		'manage_categories'  => true,
		'manage_links'  => true,
		'moderate_comments'  => true,
		'publish_pages'  => true,
		'publish_posts'  => true,
		'read'  => true,
		'read_private_pages'  => true,
		'read_private_posts'  => true,
		'unfiltered_html' => true,
		'upload_files'  => true,
		'edit_theme_options' => true
	]);
}



/**
 * Uses the articles parent slugs gathered from the front ends url, and fetches data related to them.
 * If one or more of those slugs don't exist as a page/post/category it will not be returned in the array.
 * @param {arr} $parentSlugArray A list of all parent url items from the frontend relating to the article
 * @return {arr} [{ 'name','slug','type'},]
 * @author Cein
 */
function fetchTypeBySlugs( $parentSlugArray ) {
    global $wpdb;
    $returnArray = [];

    foreach( $parentSlugArray as $parentSlug ) {
         // search pages & posts
        $queryPages = "
        SELECT 
            `ID`,
            `post_title` as name,
            `post_name` as slug,
            `post_type` as type
        FROM `wp_posts`
        WHERE
            `post_name` = %s AND
            ( `post_type` = 'page' OR `post_type` = 'post' OR `post_type` = 'tutorials' )
        ";
        $queryPagesResults = $wpdb->get_results( $wpdb->prepare($queryPages, [$parentSlug] ) , 'OBJECT' );

        if( count( $queryPagesResults ) > 0 ) {
            array_push($returnArray, $queryPagesResults[0] );
        }

        // search categories
        $queryCategories = "
        SELECT 
            terms.`term_id`,
            terms.`name`,
            terms.`slug`,
            'category' AS 'type'
        FROM `wp_terms` AS terms
        LEFT JOIN `wp_term_taxonomy` AS termTax ON terms.`term_id` = termTax.`term_id`
        WHERE
            terms.`slug` = %s AND
            termTax.taxonomy <> 'nav_menu'
        ";
        $queryCategoriesResults = $wpdb->get_results( $wpdb->prepare($queryCategories, [$parentSlug]) , 'OBJECT' );

        if( count( $queryCategoriesResults ) > 0 ) {
            array_push($returnArray, $queryCategoriesResults[0] );
        }
        
    }
    return $returnArray;
}



/**
 * End pont to get all categories and their children
 * @param {str} articleSlug the slug of the article to fetch full data on 
 * @param {str} articleType the slug type, either 'page' or 'post'
 * @param {arr} parentSlugArray string array of all url params besides the article slug, used to verify if that path could exist
 * @returns {obj} {
 *  slugResults : all article data
 *  parentSlugResults : data related to the url path
 * }
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/fetchArticleValidatePath', array(
        'methods'  => 'POST',
        'callback' => function () {

            $requestBodyData = json_decode( file_get_contents('php://input') );
            $articleSlug =  $requestBodyData->articleSlug;
            $articleType =  $requestBodyData->articleType;
            $parentSlugArray =  $requestBodyData->parentSlugArray;
            $parentSlugResults = [];
            if( !isset($articleType) || $articleSlug === '' ) {
                return new WP_Error( 'Article Not Found.', 'Article slug given is blank.', array( 'status' => 404 ) );
            }
            if( !isset($articleType) || ( $articleType !== 'post' && $articleType !== 'page' ) ) {
                return new WP_Error( 'Article Type Not Defined Correctly.', 'Article Type must be defined as post or page.', array( 'status' => 404 ) );
            }
            
            // check the parent path
			if( sizeof($parentSlugArray) > 0  ) {
                $parentSlugResults = fetchTypeBySlugs($parentSlugArray);
                if ( count($parentSlugArray) !== count($parentSlugResults)  ) {
                    return new WP_Error( 'Article Not Found.', 'Invalid Path Variables.', array( 'status' => 404 ) );
                }
            }
            
            // fetch the published page
            $args = array(
                'name'        => $articleSlug,
                'post_type'   => $articleType,
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $slugData = get_posts($args);
            if( count( $slugData ) === 0 ) {
                return new WP_Error( 'Article Not Found.', 'There is no article by that slug.', array( 'status' => 404 ) );
            }

            return [
                'slugResults' => $slugData,
                'parentSlugResults' => $parentSlugResults
            ];
        },
    ) );
});


/**
 * End point to fetch a tutorial page and validate the url to it
 * @param {str} tutorialSlug the slug of the tutorial page to fetch full data on 
 * @param {arr} parentSlugArray string array of all url params besides the article slug, used to verify if that path could exist
 * @returns {obj} {
 *  slugResults : all tutorial page data
 *  parentSlugResults : data related to the url path
 * }
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/fetchTutorialPageValidatePath', array(
        'methods'  => 'POST',
        'callback' => function () {
            global $wpdb;
            
            $requestBodyData = json_decode( file_get_contents('php://input') );
            $tutorialSlug = $requestBodyData->tutorialSlug;
            $parentSlugArray =  $requestBodyData->parentSlugArray;
            $parentSlugResults = [];

            if( !isset($tutorialSlug) || $tutorialSlug === '' ) {
                return new WP_Error( 'Tutorial Slug Not Defined.', 'Tutorial slug must be given.', array( 'status' => 404 ) );
            }
            
            // check the parent path
			if( sizeof($parentSlugArray) > 0  ) {
                $parentSlugResults = fetchTypeBySlugs($parentSlugArray);
                if ( count($parentSlugArray) !== count($parentSlugResults)  ) {
                    return new WP_Error( 'Tutorial Not Found.', 'Invalid Path Variables.', array( 'status' => 404 ) );
                }
            }
            
            // fetch the published page
            $tutoralPageData = $wpdb->get_results( $wpdb->prepare( 
                'SELECT 
                    `wp_posts`.* 
                FROM `wp_posts`  
                WHERE 
                    `wp_posts`.`post_name` = %s AND 
                    `wp_posts`.`post_type` = "tutorials" AND
                    `wp_posts`.`post_status` = "publish"
                ORDER BY `wp_posts`.`post_date` DESC', [$tutorialSlug]
            ));
                                
            if( count( $tutoralPageData ) === 0 ) {
                return new WP_Error( 'Tutorial Not Found.', 'There is no tutorial by that slug.', array( 'status' => 404 ) );
            }

            return [
                'slugResults' => $tutoralPageData,
                'parentSlugResults' => $parentSlugResults
            ];
        },
    ) );
});


/**
 * Builds the select options values for the side menu dropdown select
 * @param {str} $selectedSlugValue the value of the slug that was selected after wordpress fetch
 * @return {str} html option fields for a html select
 * @author Cein
 */
function buildSideMenuSelectOptionHtml( $selectedSlugValue ) {
    global $wpdb;
    $selectOptionsHtml = '<option value="" >None</option>';

    $menuListQuery = "
    SELECT 
        terms.`term_id`, 
        terms.`name`,
        terms.`slug`,
        termTax.`taxonomy`
    FROM `wp_terms` AS terms
    LEFT JOIN `wp_term_taxonomy` AS termTax ON termTax.term_id = terms.term_id
    WHERE 
        terms.`slug` <> 'main' AND 
        termTax.taxonomy = 'nav_menu'
    ";

    $menuList = $wpdb->get_results( $menuListQuery );

    $selectOptionsHtml = '<option value="" >None</option>';
    if( count($menuList) > 0 ) {
        foreach( $menuList as $menuItem ) {
            $selected = ( $menuItem->slug === $selectedSlugValue ? 'selected': '' );
            $selectOptionsHtml .= '<option value="'.$menuItem->slug.'" '.$selected.'>'.$menuItem->name.'</option>';
        }
    }
    return $selectOptionsHtml;
}


/**
 * Custom meta box for the pages section which will add, save and store menu items to a page/post 
 * @author Cein
 */
add_action( 'add_meta_boxes', 'side_menu_slug_hook' );
/**
 * Adds a hook into the pages/posts to add a right side menu parameter
 * @author Cein
 */
function side_menu_slug_hook() {
    add_meta_box( 'side-menu-link-id', 'Side Menu Linking', 'build_Side_Menu_Dropdown', ['page','post'] , 'side' );
}
/**
 * Build the select side menu select and set its saved value
 * @param {obj} $post full post data before saving
 * @author Cein
 */
function build_Side_Menu_Dropdown( $post ) {
    $postData = get_post_custom( $post->ID );
    $selectedSlugArray = ( isset($postData['article_side_menu_slug']) ? $postData['article_side_menu_slug'] : [] );
    $selectedSlugValue = ( count($selectedSlugArray) > 0 ? $selectedSlugArray[0] : '' );
    $selectedOptionsHtml = buildSideMenuSelectOptionHtml( $selectedSlugValue );

    ?>
        <div class="components-base-control editor-page-attributes__parent">
            <div class="components-base-control__field">
                <label for="article_side_menu_slug" class="components-base-control__label">Link a Side Menu:</label>
                <select id="article_side_menu_slug" name="article_side_menu_slug" class="components-select-control__input">
                    <?php echo $selectedOptionsHtml; ?>
                </select>
            </div>
        </div>
        
    <?php
}
// hooking into the page/post save
add_action( 'save_post', 'side_menu_slug_save' );
/**
 * Saves the side menu link to the page/post id
 * @param {int} $post_id the id of the page/post being saved
 * @author Cein
 */
function side_menu_slug_save( $post_id ){
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return false;
    }
    if( !current_user_can( 'edit_post', $post_id ) || !current_user_can( 'edit_page', $post_id ) ) {
        return false;
    }
    if( isset( $_POST['article_side_menu_slug'] ) ) {
        update_post_meta( $post_id, 'article_side_menu_slug', $_POST['article_side_menu_slug']);
    }
}


/**
 * End point to fetch a article and validate the url to it
 * @param {str} articleSlug the slug of the article to fetch full data on 
 * @param {str} articleType the slug type, either 'page' or 'post'
 * @param {arr} parentSlugArray string array of all url params besides the article slug, used to verify if that path could exist
 * @returns {obj} {
 *  slugResults : all article data
 *  parentSlugResults : data related to the url path,
 *  sideMenu: data related to any linked menu
 * }
 * @author Cein
 */
add_action( 'rest_api_init', function ( $server ) {
    $server->register_route( 'wp/v2', '/wp/v2/fetchArticleValidatePath', array(
        'methods'  => 'POST',
        'callback' => function () {
            $requestBodyData = json_decode( file_get_contents('php://input') );
            $articleSlug =  $requestBodyData->articleSlug;
            $articleType =  $requestBodyData->articleType;
            $parentSlugArray =  $requestBodyData->parentSlugArray;
            $parentSlugResults = [];

            if( !isset($articleType) || $articleSlug === '' ) {
                return new WP_Error( 'Article Not Found.', 'Article slug given is blank.', array( 'status' => 404 ) );
            }
            if( !isset($articleType) || ( $articleType !== 'post' && $articleType !== 'page' ) ) {
                return new WP_Error( 'Article Type Not Defined Correctly.', 'Article Type must be defined as post or page.', array( 'status' => 404 ) );
            }

            // check the parent path
			if( sizeof($parentSlugArray) > 0  ) {
                $parentSlugResults = fetchTypeBySlugs($parentSlugArray);
                if ( count($parentSlugArray) !== count($parentSlugResults)  ) {
                    return new WP_Error( 'Article Not Found.', 'Invalid Path Variables.', array( 'status' => 404 ) );
                }

                // fetch any associated menus to the parent items
                $parentSlugResults = checkSlugForLinkedMenus( $parentSlugResults );
            }

            // fetch the published page
            $args = array(
                'name'        => $articleSlug,
                'post_type'   => $articleType,
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $articleSlugData = get_posts($args);
            if( count( $articleSlugData ) === 0 ) {
                return new WP_Error( 'Article Not Found.', 'There is no article by that slug.', array( 'status' => 404 ) );
            }
            // check for linked menu associated to this page
            $articleSlugData = checkSlugForLinkedMenus( $articleSlugData );

            return [
                'slugResults' => $articleSlugData,
                'parentSlugResults' => $parentSlugResults
            ];
        },
    ) );
});


/**
 * This fetches all linked menus associated to any given slug and adds it to the array given
 * @param {arr} the populated results of both or either the category or post/page fetch data array
 * @author Cein
 */
function checkSlugForLinkedMenus( $arrayOfItemsToFetchLinkedMenus ) {
    $itemArrayToBuildOff = $arrayOfItemsToFetchLinkedMenus;
    if( !empty( $itemArrayToBuildOff ) ) {
        foreach( $itemArrayToBuildOff as $arrayPosition => $itemObject ) {
            $fetchedMenuArray = [];
            if( isset( $itemObject->ID ) ) {
                // check if a page or post has a menu linked to it
                $pageCustomData = get_post_custom( $itemObject->ID );
                $articleSideMenuSlugArray = ( isset($pageCustomData['article_side_menu_slug']) ? $pageCustomData['article_side_menu_slug'] : [] );
                $fetchedMenuArray = fetchMenusBySlug( $articleSideMenuSlugArray );
            } else if ( isset( $itemObject->term_id ) ) {
                // check if category has a menu
                $categoryMetaData = get_option( 'category_'.$itemObject->term_id);
                if( isset( $categoryMetaData['article_side_menu_slug'] ) && $categoryMetaData['article_side_menu_slug'] !== '' ) {
                    $menuSlug = $categoryMetaData['article_side_menu_slug'];
                    $fetchedMenuArray = fetchMenusBySlug( [ $menuSlug ] );
                }
            }
            
            $itemArrayToBuildOff[$arrayPosition]->linked_menu = $fetchedMenuArray;
        }
    }

    return $itemArrayToBuildOff;
}

/**
 * Fetches menus by the given slug list
 * @param {arr} list of slugs
 * @return {arr} array of all fetched menu data
 * @author Cein
 */
function fetchMenusBySlug( $menuSlugArray ) {
    $fetchedMenuArray = [];
    if (!empty($menuSlugArray)) {
        foreach( $menuSlugArray as $menuSlug ) {
            $slugsSideMenuArray = wp_get_nav_menu_items( $menuSlug );
            if ( !empty($slugsSideMenuArray )) {
                array_push( $fetchedMenuArray, $slugsSideMenuArray );
            }
        }
    }

    return $fetchedMenuArray;
}

