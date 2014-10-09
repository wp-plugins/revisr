<?php
/**
 * class-revisr-setup.php
 *
 * Installs and configures Revisr.
 * @package Revisr
 * @license GPLv3
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts, LLC
 */

class Revisr_Setup
{
	/**
	 * The WordPress database object.
	 */
	public $wpdb;

	/**
	 * The main Git class.
	 */
	public $git;

	/**
	 * User options and preferences. 
	 */
	public $options;

	/**
	 * The current directory.
	 */
	public $dir;

	/**
	 * Load items necessary for setup.
	 * @access public
	 */
	public function __construct( $options ) {
		global $wpdb;
		$this->wpdb 	= $wpdb;
		$this->options 	= $options;
		$this->git 		= new Revisr_Git();
		$this->dir 		= plugin_dir_path( __FILE__ );
	}

	/**
	 * Registers and enqueues css and javascript files.
	 * @access public
	 * @param string $hook The page to enqueue the styles/scripts.
	 */
	public function revisr_scripts( $hook ) {
		wp_register_style( 'revisr_dashboard_css', plugins_url() . '/revisr/assets/css/dashboard.css', array(), '07052014' );
		wp_register_style( 'revisr_commits_css', plugins_url() . '/revisr/assets/css/commits.css', array(), '08202014' );
		wp_register_script( 'revisr_dashboard', plugins_url() . '/revisr/assets/js/dashboard.js', 'jquery',  '09232014' );
		wp_register_script( 'revisr_staging', plugins_url() . '/revisr/assets/js/staging.js', 'jquery', '07052014', false );
		wp_register_script( 'revisr_committed', plugins_url() . '/revisr/assets/js/committed.js', 'jquery', '07052014', false );
		wp_register_script( 'revisr_settings', plugins_url() . '/revisr/assets/js/settings.js', 'jquery', '08272014', true );
		$allowed_pages = array( 'revisr', 'revisr_settings', 'revisr_branches' );
		//Enqueue common styles and scripts.
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $allowed_pages ) ) {
			wp_enqueue_style( 'revisr_dashboard_css' );
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'revisr_settings' );
		}
		//Enqueue styles and scripts on the Revisr staging area.
		if ( $hook == 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == "revisr_commits" ) {
			wp_enqueue_script( 'revisr_staging' );
			wp_localize_script( 'revisr_staging', 'pending_vars', array(
				'ajax_nonce' => wp_create_nonce( 'pending_nonce' ),
				)
			);
		}
		//Enqueue styles and scripts for viewing a commit.
		if ( $hook == 'post.php' && get_post_type() == 'revisr_commits' ) {
			wp_enqueue_script( 'revisr_committed' );
			wp_localize_script( 'revisr_committed', 'committed_vars', array(
				'post_id' => $_GET['post'],
				'ajax_nonce' => wp_create_nonce( 'committed_nonce' ),
				)
			);			
		}
		//Add styles and scripts to commits pages.
		if ( get_post_type() == 'revisr_commits' || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'revisr_commits' ) {
			wp_enqueue_style( 'revisr_commits_css' );
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'thickbox' );
		}
	}

	/**
	 * Registers the "revisr_commits" post type.
	 * @access public
	 */
	public function revisr_post_types() {
		$labels = array(
			'name'                => __( 'Commits', 'revisr' ),
			'singular_name'       => __( 'Commit', 'revisr' ),
			'menu_name'           => __( 'Commits', 'revisr' ),
			'parent_item_colon'   => '',
			'all_items'           => __( 'Commits', 'revisr' ),
			'view_item'           => __( 'View Commit', 'revisr' ),
			'add_new_item'        => __( 'New Commit', 'revisr' ),
			'add_new'             => __( 'New Commit', 'revisr' ),
			'edit_item'           => __( 'Edit Commit', 'revisr' ),
			'update_item'         => __( 'Update Commit', 'revisr' ),
			'search_items'        => __( 'Search Commits', 'revisr' ),
			'not_found'           => __( 'No commits found yet, why not create a new one?', 'revisr' ),
			'not_found_in_trash'  => __( 'No commits in trash.', 'revisr' ),
		);
		$capabilities = array(
			'edit_post'           => 'activate_plugins',
			'read_post'           => 'activate_plugins',
			'delete_post'         => 'activate_plugins',
			'edit_posts'          => 'activate_plugins',
			'edit_others_posts'   => 'activate_plugins',
			'publish_posts'       => 'activate_plugins',
			'read_private_posts'  => 'activate_plugins',
		);
		$args = array(
			'label'               => 'revisr_commits',
			'description'         => __( 'Commits made through Revisr', 'revisr' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'author' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'revisr',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => '',
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capabilities'        => $capabilities,
		);
		register_post_type( 'revisr_commits', $args );
	}

	/**
	 * Adds custom meta boxes to the "revisr_commits" post type.
	 * @access public
	 */
	public function meta() {
		if ( isset( $_GET['action'] ) ) {
			if ( 'edit' == $_GET['action'] ) {
				add_meta_box( 'revisr_committed_files', __( 'Committed Files', 'revisr' ), array( $this, 'committed_files_meta' ), 'revisr_commits', 'normal', 'high' );
			}			
		} else {
			add_meta_box( 'revisr_pending_files', __( 'Stage Changes', 'revisr' ), array( $this, 'pending_files_meta' ), 'revisr_commits', 'normal', 'high' );
			add_meta_box( 'revisr_add_tag', __( 'Add Tag', 'revisr' ), array( $this, 'add_tag_meta' ), 'revisr_commits', 'side', 'default' );
		}
	}

	/**
	 * Displays the "Add Tag" meta box on the sidebar.
	 * @access public
	 */
	public function add_tag_meta() {
		echo "<label for='tag_name'><p>" . __( 'Tag Name:', 'revisr' ) . '</p></label>';
		echo "<input id='tag_name' type='text' name='tag_name' />";
	}

	/**
	 * Displays the files changed in a commit.
	 * @access public
	 */
	public function committed_files_meta() {
		echo "<div id='committed_files_result'></div>";
	}

	/**
	 * The container for the staging area.
	 * @access public
	 */
	public function pending_files_meta() {
		echo "<div id='message'></div>
		<div id='pending_files_result'></div>";
	}	
	
	/**
	 * Registers the menus used by Revisr.
	 * @access public
	 */
	public function menus() {
		$menu = add_menu_page( 'Dashboard', 'Revisr', 'manage_options', 'revisr', array( $this, 'revisr_dashboard' ), plugins_url( 'revisr/assets/img/white_18x20.png' ) );
		add_submenu_page( 'revisr', 'Revisr - Dashboard', 'Dashboard', 'manage_options', 'revisr', array( $this, 'revisr_dashboard' ) );
		add_submenu_page( 'revisr', 'Revisr - Branches', 'Branches', 'manage_options', 'revisr_branches', array( $this, 'revisr_branches' ) );
		add_submenu_page( 'revisr', 'Revisr - Settings', 'Settings', 'manage_options', 'revisr_settings', array( $this, 'revisr_settings' ) );
		add_action( 'admin_print_scripts-' . $menu, array( $this, 'revisr_scripts' ) );
		remove_meta_box( 'authordiv', 'revisr_commits', 'normal' );
	}

	/**
	 * Filters the display order of the menu pages.
	 * @access public
	 */
	public function revisr_commits_submenu_order( $menu_ord ) {
		global $submenu;
	    $arr = array();
	    
		if ( isset( $submenu['revisr'] ) ) {
		    $arr[] = $submenu['revisr'][0];
		    $arr[] = $submenu['revisr'][3];
		    $arr[] = $submenu['revisr'][1];
		    $arr[] = $submenu['revisr'][2];
		    $submenu['revisr'] = $arr;
		}
	    return $menu_ord;
	}

	/**
	 * Includes the template for the main dashboard.
	 * @access public
	 */
	public function revisr_dashboard() {
		include_once $this->dir . "../templates/dashboard.php";
	}

	/**
	 * Includes the template for the branches page.
	 * @access public
	 */
	public function revisr_branches() {
		include_once $this->dir . "../templates/branches.php";
	}

	/**
	 * Includes the template for the settings page.
	 * @access public
	 */
	public function revisr_settings() {
		include_once $this->dir . "../templates/settings.php";
	}

	/**
	 * Adds the custom actions to the Commits list.
	 * @access public
	 * @param array $actions The default array of actions.
	 */
	public function custom_actions( $actions ) {
		if ( get_post_type() == 'revisr_commits' ) {
			if ( isset( $actions ) ) {
				unset( $actions['edit'] );
		        unset( $actions['view'] );
		        unset( $actions['trash'] );
		        unset( $actions['inline hide-if-no-js'] );

		        $id 				= get_the_ID();
		        $url 				= get_admin_url() . 'post.php?post=' . get_the_ID() . '&action=edit';
		        $actions['view'] 	= "<a href='{$url}'>View</a>";
		        $branch_meta 		= get_post_custom_values( 'branch', get_the_ID() );
		        $db_hash_meta 		= get_post_custom_values( 'db_hash', get_the_ID() );
		        $commit_hash 		= Revisr_Git::get_hash( $id );
		        $revert_nonce 		= wp_nonce_url( admin_url("admin-post.php?action=process_revert&commit_hash={$commit_hash}&branch={$branch_meta[0]}&post_id=" . get_the_ID()), 'revert', 'revert_nonce' );
		        $actions['revert'] 	= "<a href='" . $revert_nonce . "'>" . __( 'Revert Files', 'revisr' ) . "</a>";
		        
		        if ( is_array( $db_hash_meta ) ) {
		        	$db_hash 			= str_replace( "'", "", $db_hash_meta );
		        	$revert_db_nonce 	= wp_nonce_url( admin_url("admin-post.php?action=revert_db&db_hash={$db_hash[0]}&branch={$branch_meta[0]}&post_id=" . get_the_ID()), 'revert_db', 'revert_db_nonce' );
			        if ( $db_hash[0] != '') {
		          		$actions['revert_db'] = "<a href='" . $revert_db_nonce ."'>" . __( 'Revert Database', 'revisr' ) . "</a>";
			        }		        	
		        }        
			}
		}
		return $actions;
	}

	/**
	 * Filters commits by branch.
	 * @access public
	 * @param object $commits The commits query.
	 */
	public function filters( $commits ) {
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == "revisr_commits" ) {
			if ( isset( $_GET['branch'] ) && $_GET['branch'] != "all" ) {
				$commits->set( 'meta_key', 'branch' );
				$commits->set( 'meta_value', $_GET['branch'] );
				$commits->set( 'post_type', 'revisr_commits' );
			}
		}
		return $commits;
	}

	/**
	 * Counts the number of commits on a given branch.
	 * @access public
	 * @param string $branch The name of the branch to count commits for.
	 */
	public function count_commits( $branch ) {
		if ($branch == "all") {
			$num_commits = $this->wpdb->get_results( "SELECT * FROM " . $this->wpdb->postmeta . " WHERE meta_key = 'branch'" );
		} else {
			$num_commits = $this->wpdb->get_results( "SELECT * FROM " . $this->wpdb->postmeta . " WHERE meta_key = 'branch' AND meta_value = '".$branch."'" );
		}
		return count( $num_commits );
	}

	/**
	 * Unsets unused views, replaced with branches.
	 * @access public
	 * @param array $views The global views for the post type.
	 */
	public function custom_views( $views ) {

		$output = $this->git->run( 'branch' );

		global $wp_query;

		if ( is_array( $output ) ) {
			foreach ( $output as $key => $value ) {
				$branch = substr( $value, 2 );
	    	    $class = ( $wp_query->query_vars['meta_value'] == $branch ) ? ' class="current"' : '';
		    	$views["$branch"] = sprintf( __( '<a href="%s"'. $class .'>' . ucwords( $branch ) . ' <span class="count">(%d)</span></a>' ),
		        admin_url( 'edit.php?post_type=revisr_commits&branch='.$branch ),
		        $this->count_commits( $branch ) );
			}
			if ( $_GET['branch'] == "all" ) {
				$class = 'class="current"';
			} else {
				$class = '';
			}
			$views['all'] = sprintf( __( '<a href="%s"' . $class . '>All Branches <span class="count">(%d)</span></a>', 'revisr' ),
				admin_url( 'edit.php?post_type=revisr_commits&branch=all' ),
				$this->count_commits( "all" ));
			unset( $views['publish'] );
			unset( $views['draft'] );
			unset( $views['trash'] );
			if ( isset( $views ) ) {
				return $views;
			}
		}

	}

	/**
	 * Sets the default view to the current branch on the commit listing.
	 * @access public
	 */
	public function default_views() {
		if( !isset($_GET['branch'] ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == "revisr_commits") {
			$_GET['branch'] = $this->git->branch;
		}
	}
	/**
	 * Displays the number of files changed in the admin bar.
	 * @access public
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( $this->git->count_untracked() != 0 ) {
			$untracked 	= $this->git->count_untracked();
			$text 		= sprintf( _n( '%s Untracked File', '%s Untracked Files', $untracked, 'revisr' ), $untracked );
			$args 		= array(
				'id'    => 'revisr',
				'title' => $text,
				'href'  => get_admin_url() . 'post-new.php?post_type=revisr_commits',
				'meta'  => array( 'class' => 'revisr_commits' ),
			);
			$wp_admin_bar->add_node( $args );
		} 
	}

	/**
	 * Disables autodraft when on the new commit page.
	 * @access public
	 */
	public function disable_autodraft() {
		if ( "revisr_commits" == get_post_type() ) {
			wp_dequeue_script( 'autosave' );
		}
	}

	/**
	 * Displays custom columns for the commits post type.
	 * @access public
	 */
	public function columns() {
		$columns = array (
			'cb' 			=> '<input type="checkbox" />',
			'hash' 			=> __( 'ID', 'revisr' ),
			'title' 		=> __( 'Commit', 'revisr' ),
			'branch' 		=> __( 'Branch', 'revisr' ),
			'tag' 			=> __( 'Tag', 'revisr' ),
			'files_changed' => __( 'Files Changed', 'revisr' ),
			'date' 			=> __( 'Date', 'revisr' ),
		);
		return $columns;
	}

	/**
	 * Displays the number of committed files and the commit hash for commits.
	 * @access public
	 * @param string $column The column to add.
	 */
	public function custom_columns( $column ) {
		global $post;

		$post_id = get_the_ID();
		switch ( $column ) {
			case "hash": 
				echo Revisr_Git::get_hash( $post_id );
			break;
			case "branch":
				$branch_meta = get_post_meta( $post_id, "branch" );
				if ( isset( $branch_meta[0] ) ) {
					echo $branch_meta[0];
				}
			break;
			case "tag":
				$tag_meta = get_post_meta( $post_id, "git_tag" );
				if ( isset( $tag_meta[0] ) ) {
					echo $tag_meta[0];
				}
			break;
			case "files_changed":
				$files_meta = get_post_meta( $post_id, "files_changed" );
				if ( isset( $files_meta[0] ) ) {
					echo $files_meta[0];
				}
			break;
		}

	}

	/**
	 * Custom messages for commits.
	 * @access public
	 * @param array $messages The messages to pass back to the commits.
	 */
	public function revisr_commits_custom_messages( $messages ) {
		$post = get_post();
		$messages['revisr_commits'] = array(
		0  => '', // Unused. Messages start at index 1.
		1  => __( 'Commit updated.', 'revisr_commits' ),
		2  => __( 'Custom field updated.', 'revisr_commits' ),
		3  => __( 'Custom field deleted.', 'revisr_commits' ),
		4  => __( 'Commit updated.', 'revisr_commits' ),
		/* translators: %s: date and time of the revision */
		5  => isset( $_GET['revision'] ) ? sprintf( __( 'Commit restored to revision from %s', 'revisr_commits' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => __( 'Committed files on branch <strong>' . $this->git->branch . '</strong>.', 'revisr_commits' ),
		7  => __( 'Commit saved.', 'revisr_commits' ),
		8  => __( 'Commit submitted.', 'revisr_commits' ),
		9  => sprintf(
			__( 'Commit scheduled for: <strong>%1$s</strong>.', 'revisr_commits' ),
			// translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i', 'revisr_commits' ), strtotime( $post->post_date ) )
		),
		10 => __( 'Commit draft updated.', 'revisr_commits' ),
		);

		return $messages;
	}

	/**
	 * Custom bulk messages for Revisr.
	 * @access public
	 * @param array $bulk_messages The messages to display. 
	 * @param array $bulk_counts   The number of those messages.
	 */
	public function revisr_commits_bulk_messages( $bulk_messages, $bulk_counts ) {
		$bulk_messages['revisr_commits'] = array(
			'updated' 	=> _n( '%s commit updated.', '%s commits updated.', $bulk_counts['updated'] ),
			'locked'    => _n( '%s commit not updated, somebody is editing it.', '%s commits not updated, somebody is editing them.', $bulk_counts['locked'] ),
			'deleted'   => _n( '%s commit permanently deleted.', '%s commits permanently deleted.', $bulk_counts['deleted'] ),
			'trashed'   => _n( '%s commit moved to the Trash.', '%s commits moved to the Trash.', $bulk_counts['trashed'] ),
        	'untrashed' => _n( '%s commit restored from the Trash.', '%s commits restored from the Trash.', $bulk_counts['untrashed'] )
        	);
		return $bulk_messages;
	}

	/**
	 * Displays the form to delete a branch.
	 * @access public
	 */
	public function delete_branch_form() {
		include_once $this->dir . "../assets/partials/delete-branch-form.php";
	}

	/**
	 * Displays the recent activity box on the dashboard.
	 * @access public
	 */
	public static function recent_activity() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'revisr';
		$revisr_events = $wpdb->get_results( "SELECT id, time, message FROM $table_name ORDER BY id DESC LIMIT 15", ARRAY_A );
		if ( $revisr_events ) {
			?>
			<table class="widefat">
				<tbody id="activity_content">
				<?php
					foreach ($revisr_events as $revisr_event) {
						$timestamp = strtotime($revisr_event['time']);
						$time  	   = sprintf( __( '%s ago', 'revisr' ), human_time_diff( $timestamp ) );
						echo "<tr><td>{$revisr_event['message']}</td><td>{$time}</td></tr>";
					}
				?>
				</tbody>
			</table>
			<?php		
		} else {
			_e( '<p id="revisr_activity_no_results">Your recent activity will show up here.</p>', 'revisr' );
		}

		if ( defined( "DOING_AJAX" ) ) {
			exit();
		}
	}
	
	/**
	 * Displays the "Sponsored by Site5" logo.
	 * @access public
	 */
	public function site5_notice() {
		$allowed_on = array( 'revisr', 'revisr_settings', 'revisr_commits', 'revisr_settings', 'revisr_branches' );
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $allowed_on ) ) {
			$output = true;
		} else if ( isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], $allowed_on ) || get_post_type() == 'revisr_commits' ) {
			$output = true;
		} else {
			$output = false;
		}
		if ( $output === true ) {
			?>
			<div id="site5_wrapper">
				<?php _e( 'Sponsored by', 'revisr' ); ?>
				<a href="http://www.site5.com/" target="_blank"><img id="site5_logo" src="<?php echo plugins_url( 'revisr/assets/img/site5.png' ); ?>" width="80" /></a>
			</div>
			<?php
		}
	}
}