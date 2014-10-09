<?php
/**
 * class-revisr-git-callback.php
 * 
 * Processes Git responses and errors.
 * 
 * @package   Revisr
 * @license   GPLv3
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts, LLC
 */
class Revisr_Git_Callback extends Revisr_Git
{
	/**
	 * Callback for a successful checkout.
	 * @access public
	 */
	public function success_checkout( $output = '', $args = '' ) {
		$branch 	= $this->branch;
		$msg 		= sprintf( __( 'Checked out branch: %s.', 'revisr' ), $branch );
		$email_msg 	= sprintf( __( '%s was switched to branch %s.', 'revisr' ), get_bloginfo(), $branch );
		Revisr_Admin::alert( $msg );
		Revisr_Admin::log( $msg, "branch" );
		Revisr_Admin::notify(get_bloginfo() . __( ' - Branch Changed', 'revisr'), $email_msg );
	}

	/**
	 * Callback for a failed checkout.
	 * @access public
	 */
	public function null_checkout( $output = '', $args = '' ) {
		$msg = __( 'There was an error checking out the branch. Check your configuration and try again.', 'revisr' );
		Revisr_Admin::alert( $msg, true );
		Revisr_Admin::log( $msg, 'error' );
	}

	/**
	 * Callback for a successful commit.
	 * @access public
	 */
	public function success_commit( $output = '', $args = '' ) {
		$id 			= get_the_ID();
		$view_link 		= get_admin_url() . "post.php?post={$id}&action=edit";
		$commit_hash 	= $this->current_commit();
		$commit_msg 	= $_REQUEST['post_title'];
		add_post_meta( $id, 'commit_hash', $commit_hash );
		add_post_meta( $id, 'branch', $this->branch );
		//Backup the database if necessary
		if ( isset( $_REQUEST['backup_db'] ) && $_REQUEST['backup_db'] == 'on' ) {
			$db = new Revisr_DB;
			$db->backup();
			$db_hash = $this->run( "log --pretty=format:'%h' -n 1" );
			add_post_meta( $id, 'db_hash', $db_hash[0] );
		}
		//Log the event.
		$msg = sprintf( __( 'Commmitted <a href="%s">#%s</a> to the local repository.', 'revisr' ), $view_link, $commit_hash );
		Revisr_Admin::log( $msg, 'commit' );
		//Notify the admin.
		$email_msg = sprintf( __( 'A new commit was made to the repository: <br> #%s - %s', 'revisr' ), $commit_hash, $commit_msg );
		Revisr_Admin::notify( get_bloginfo() . __( ' - New Commit', 'revisr' ), $email_msg );
		//Add a tag if necessary.
		if ( isset( $_REQUEST['tag_name'] ) ) {
			$this->tag( $_POST['tag_name'] );
			add_post_meta( $id, 'git_tag', $_POST['tag_name'] );
		}
		//Push if necessary.
		$this->auto_push();
		return $commit_hash;
	}

	/**
	 * Callback for a failed commit.
	 * @access public
	 */
	public function null_commit( $output = '', $args = '' ) {
		$msg = __( 'Error committing the changes to the local repository.', 'revisr' );
		Revisr_Admin::log( $msg, 'error' );
	}

	/**
	 * Callback for successful branch deletion.
	 * @access public
	 */
	public function success_delete_branch( $output = '', $args = '' ) {
		$branch 	= $args;
		$msg 		= sprintf( __( 'Deleted branch %s.', 'revisr'), $branch );
		$email_msg 	= sprintf( __( 'The branch "%s" on the repository for %s was deleted.', 'revisr' ), $branch, get_bloginfo() );
		Revisr_Admin::log( $msg, 'branch' );
		Revisr_Admin::notify( get_bloginfo() . __( ' - Branch Deleted', 'revisr' ), $email_msg );
		echo "<script>
				window.top.location.href = '" . get_admin_url() . "admin.php?page=revisr_branches&status=delete_success&branch={$branch}'
		</script>";
	}

	/**
	 * Callback for a failed branch deletion.
	 * @access public
	 */
	public function null_delete_branch( $output = '', $args = '' ) {
		echo "<script>
				window.top.location.href = '" . get_admin_url() . "admin.php?page=revisr_branches&status=delete_fail'
		</script>";
	}

	/**
	 * Renders the number of unpushed/unpulled commits for the AJAX buttons.
	 * @access public
	 */
	public function success_count_ajax_btn( $output = '', $args = '' ) {
		if ( count( $output ) != 0 ) {
			echo '(' . count( $output ) . ')';
		}
		exit();
	}

	/**
	 * Returns nothing if there are no commits to push/pull.
	 * @access public
	 */
	public function null_count_ajax_btn( $output = '', $args = '' ) {
		exit();
	}

	/**
	 * Returns if the repo initialization was successful.
	 * @access public
	 */
	public function success_init_repo() {
		Revisr_Admin::clear_transients();
		Revisr_Admin::log( __( 'Initialized a new repository.', 'revisr' ), 'init' );
		if ( isset( $this->options['username'] ) && $this->options['username'] != "" ) {
			$this->config_user_name( $this->options['username'] );
		}
		if ( isset( $this->options['email'] ) && $this->options['email'] != "" ) {
			$this->config_user_email( $this->options['email'] );
		}
		if ( isset( $this->options['remote_name'] ) && $this->options['remote_name'] != "" ) {
			$remote_name = $this->options['remote_name'];
		} else {
			$remote_name = 'origin';
		}
		if ( isset( $this->options['remote_url'] ) && $this->options['remote_url'] != "" ) {
			$this->run("remote add $remote_name {$this->options['remote_url']}");
		}
		$settings_link 	= get_admin_url() . 'admin.php?page=revisr_settings';
		$commit_link 	= get_admin_url() . 'post-new.php?post_type=revisr_commits';
		$alert_msg 		= sprintf( __( 'Successfully initialized a new repository. Please confirm your <a href="%s">settings</a> before creating your first <a href="%s">commit</a>.', 'revisr' ), $settings_link, $commit_link );
		Revisr_Admin::alert( $alert_msg );
		wp_redirect( get_admin_url() . 'admin.php?page=revisr' );
		exit();
	}

	/**
	 * Returns if an initialization failed.
	 * @access public
	 */
	public function null_init_repo() {
		Revisr_Admin::log( __( 'Failed to initialize a new repository.', 'revisr' ), 'error' );
		wp_redirect( get_admin_url() . 'admin.php?page=revisr' );
		exit();
	}

	/**
	 * Returns if a merge was successful.
	 * @access public
	 */
	public function success_merge( $output = '', $args = '' ) {
		$alert_msg 	= sprintf( __( 'Successfully merged changes from branch %s into branch %s.', 'revisr' ), $_REQUEST['branch'], $this->branch );
		$log_msg 	= sprintf( __( 'Merged branch %s into branch %s.', 'revisr' ), $_REQUEST['branch'], $this->branch );
		Revisr_Admin::alert( $alert_msg );
		Revisr_Admin::log( $log_msg, 'merge' );
		wp_redirect( get_admin_url() . 'admin.php?page=revisr' );
		exit();
	}

	/**
	 * Returns if a merge failed.
	 * @access public
	 */
	public function null_merge( $output = '', $args = '' ) {
		$log_msg 	= sprintf( __( 'Error merging branch %s into %s.', 'revisr'), $_REQUEST['branch'], $this->branch );
		$alert_msg 	= sprintf( __( 'There was an error merging branch %s into your current branch. The merge was aborted to avoid conflicts.', 'revisr' ), $_REQUEST['branch'] );
		Revisr_Admin::alert( $alert_msg, true );
		Revisr_Admin::log( $log_msg, 'error' );
		wp_redirect( get_admin_url() . 'admin.php?page=revisr' );
		exit();
	}

	/**
	 * Returns if a pull was successful.
	 * @access public
	 */
	public function success_pull( $output = '', $args = '' ) {
		if ( $args == '0' ) {
			$msg = __( 'The local repository is already up-to-date with the remote repository.', 'revisr' );
			Revisr_Admin::alert( $msg );
		} else {
			$msg = sprintf( _n( 'Successfully pulled %s commit from %s/%s.', 'Successfully pulled %s commits from %s/%s.', $args, 'revisr' ), $args, $this->remote, $this->branch );
			Revisr_Admin::alert( $msg );
		}
	}

	/**
	 * Returns if a pull failed.
	 * @access public
	 */
	public function null_pull( $output = '', $args = '' ) {
		$msg = __( 'There was an error pulling from the remote repository. The local repository could be ahead, or there may be an authentication issue.', 'revisr' );
		Revisr_Admin::alert( $msg, true );
		Revisr_Admin::log( __( 'Error pulling changes from the remote repository.', 'revisr' ), 'error' );
		exit();
	}

	/**
	 * Returns if a push was successful.
	 * @access public
	 */
	public function success_push( $output = '', $args = '' ) {
		$msg = sprintf( _n( 'Successfully pushed %s commit to %s/%s.', 'Successfully pushed %s commits to %s/%s.', $args, 'revisr' ), $args, $this->remote, $this->branch );
		Revisr_Admin::alert( $msg );
		Revisr_Admin::log( $msg, 'push' );
	}

	/**
	 * Returns if a push failed.
	 * @access public
	 */
	public function null_push( $output = '', $args = '' ) {
		$msg = __( 'Error pushing to the remote repository. The remote repository could be ahead, or there may be an authentication issue.', 'revisr' );
		Revisr_Admin::alert( $msg, true );
		Revisr_Admin::log( __( 'Error pushing changes to the remote repository.', 'revisr' ), 'error' );
		return;
	}

	/**
	 * Returns "Success!" if the connection to remote was successful.
	 * @access public
	 */
	public function success_verify_remote(  $output = '', $args = '' ) {
		_e( 'Success!', 'revisr' );
		exit();
	}

	/**
	 * Returns if the connection to the remote was unsuccessful.
	 * @access public
	 */
	public function null_verify_remote( $output = '', $args = '' ) {
		_e( 'Remote not found...', 'revisr' );
		exit();
	} 

	/**
	 * Returns the Git version.
	 * @access public
	 */
	public function success_version( $output = '', $args = '' ) {
		return $output['0'];
	}

	/**
	 * Returns if Revisr could not detect the Git version.
	 * @access public
	 */
	public function null_version( $output = '', $args = '' ) {
		return __( 'Unknown', 'revisr' );
	}
}