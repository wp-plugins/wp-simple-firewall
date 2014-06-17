<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( dirname(__FILE__).'/icwp-base-processor.php' );

if ( !class_exists('ICWP_AutoUpdatesProcessor_V5') ):

class ICWP_AutoUpdatesProcessor_V5 extends ICWP_BaseProcessor_V3 {

	const Slug = 'autoupdates';
	
	const FilterPriority = 1001;
	
	protected $sPluginFile;
	
	/**
	 * @var boolean
	 */
	protected $m_fDoForceRunAutoUpdates = false;
	
	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::Slug );
	}
	
	/**
	 * @param boolean $infDoForceRun
	 */
	public function setForceRunAutoUpdates( $infDoForceRun ) {
		$this->m_fDoForceRunAutoUpdates = $infDoForceRun;
	}
	
	/**
	 */
	public function getForceRunAutoUpdates() {
		return apply_filters( 'icwp_force_autoupdate', $this->m_fDoForceRunAutoUpdates );
	}
	
	/**
	 */
	public function run( $sPluginFile = '' ) {
		
		$this->sPluginFile = $sPluginFile;
		
		// When we force run we only want our filters.
		if ( $this->getForceRunAutoUpdates() ) {
			$aFilters = array(
				'allow_minor_auto_core_updates',
				'allow_major_auto_core_updates',
				'auto_update_translation',
				'auto_update_plugin',
				'auto_update_theme',
				'automatic_updates_is_vcs_checkout',
				'automatic_updater_disabled'
			);
			foreach( $aFilters as $sFilter ) {
				remove_all_filters( $sFilter );
			}
		}
		
		add_filter( 'allow_minor_auto_core_updates',	array( $this, 'autoupdate_core_minor' ), self::FilterPriority );
		add_filter( 'allow_major_auto_core_updates',	array( $this, 'autoupdate_core_major' ), self::FilterPriority );

		add_filter( 'auto_update_translation',	array( $this, 'autoupdate_translations' ), self::FilterPriority, 2 );
		add_filter( 'auto_update_plugin',		array( $this, 'autoupdate_plugins' ), self::FilterPriority, 2 );
		add_filter( 'auto_update_theme',		array( $this, 'autoupdate_themes' ), self::FilterPriority, 2 );

		if ( $this->getIsOption('enable_autoupdate_ignore_vcs', 'Y') ) {
			add_filter( 'automatic_updates_is_vcs_checkout', array( $this, 'disable_for_vcs' ), 10, 2 );
		}

		if ( $this->getIsOption('enable_autoupdate_disable_all', 'Y') ) {
			add_filter( 'automatic_updater_disabled', '__return_true', self::FilterPriority );
		}
		
		add_filter( 'auto_core_update_send_email', array( $this, 'autoupdate_send_email' ), self::FilterPriority, 1 ); //more parameter options here for later
		add_filter( 'auto_core_update_email', array( $this, 'autoupdate_email_override' ), self::FilterPriority, 1 ); //more parameter options here for later

		if ( $this->getForceRunAutoUpdates() ) {
			$this->force_run_autoupdates( 'update-core.php' ); //we'll redirect to the updates page for to show
		}
	}

	/**
	 * Will force-run the WordPress automatic updates process and then redirect to the updates screen.
	 */
	public function force_run_autoupdates( $insRedirect = false ) {
		$lock_name = 'auto_updater.lock'; //ref: /wp-admin/includes/class-wp-upgrader.php
		delete_option( $lock_name );
		if ( !defined('DOING_CRON') ) {
			define( 'DOING_CRON', true ); // this prevents WP from disabling plugins pre-upgrade
		}
		
		// does the actual updating
		$this->doStatIncrement( 'autoupdates.forcerun' );
		wp_maybe_auto_update();
		
		if ( !empty( $insRedirect ) ) {
			wp_redirect( network_admin_url( $insRedirect ) );
			exit();
		}
		return true;
	}
	
	/**
	 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * 
	 * @param boolean $infUpdate
	 * @return boolean
	 */
	public function autoupdate_core_major( $infUpdate ) {
		if ( $this->getIsOption('autoupdate_core', 'core_never') ) {
			$this->doStatIncrement( 'autoupdates.core.major.blocked' );
			return false;
		}
		else if ( $this->getIsOption('autoupdate_core', 'core_major') ) {
			$this->doStatIncrement( 'autoupdates.core.major.allowed' );
			return true;
		}
		return $infUpdate;
	}
	
	/**
	 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * 
	 * @param boolean $infUpdate
	 * @return boolean
	 */
	public function autoupdate_core_minor( $infUpdate ) {
		if ( $this->getIsOption('autoupdate_core', 'core_never') ) {
			$this->doStatIncrement( 'autoupdates.core.minor.blocked' );
			return false;
		}
		else if ( $this->getIsOption('autoupdate_core', 'core_minor') ) {
			$this->doStatIncrement( 'autoupdates.core.minor.allowed' );
			return true;
		}
		return $infUpdate;
	}
	
	/**
	 * This is a filter method designed to say whether a WordPress translations upgrades should be permitted,
	 * based on the plugin settings.
	 * 
	 * @param boolean $infUpdate
	 * @param string $insSlug
	 * @return boolean
	 */
	public function autoupdate_translations( $infUpdate, $insSlug ) {
		if ( $this->getIsOption('enable_autoupdate_translations', 'Y') ) {
			return true;
		}
		return $infUpdate;
	}
	
	/**
	 * This is a filter method designed to say whether WordPress plugin upgrades should be permitted,
	 * based on the plugin settings.
	 * 
	 * @param boolean $infUpdate
	 * @param StdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdate_plugins( $infUpdate, $mItem ) {

		// first, is global auto updates for plugins set
		if ( $this->getIsOption('enable_autoupdate_plugins', 'Y') ) {
			$this->doStatIncrement( 'autoupdates.plugins.all' );
			return true;
		}

		if ( is_object( $mItem ) && isset( $mItem->plugin ) )  { // WP 3.8.2+
			$sItemFile = $mItem->plugin;
		}
		else if ( is_string( $mItem ) ) { // WP pre-3.8.2
			$sItemFile = $mItem;
		}
		// at this point we don't have a slug to use so we just return the current update setting
		else {
			return $infUpdate;
		}

		if ( $sItemFile === $this->sPluginFile ) {
			if ( $this->getIsOption('autoupdate_plugin_self', 'Y') ) {
				$this->doStatIncrement( 'autoupdates.plugins.self' );
				return true;
			}
			return false;
		}

		$aAutoUpdatePluginFiles = apply_filters( 'icwp_wpsf_autoupdate_plugins', array() );

		if ( !empty( $aAutoUpdatePluginFiles )
			&& is_array($aAutoUpdatePluginFiles)
			&& in_array( $sItemFile, $aAutoUpdatePluginFiles ) ) {

				return true;
		}

		return $infUpdate;
	}
	
	/**
	 * This is a filter method designed to say whether WordPress theme upgrades should be permitted,
	 * based on the plugin settings.
	 *
	 * @param boolean $infUpdate
	 * @param stdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdate_themes( $infUpdate, $mItem ) {

		// first, is global auto updates for themes set
		if ( $this->getIsOption('enable_autoupdate_themes', 'Y') ) {
			$this->doStatIncrement( 'autoupdates.themes.all' );
			return true;
		}

		if ( is_object( $mItem ) && isset( $mItem->theme ) ) { // WP 3.8.2+
			$sItemFile = $mItem->theme;
		}
		else if ( is_string( $mItem ) ) { // WP pre-3.8.2
			$sItemFile = $mItem;
		}
		// at this point we don't have a slug to use so we just return the current update setting
		else {
			return $infUpdate;
		}

		$aAutoUpdateThemeFiles = apply_filters( 'icwp_wpsf_autoupdate_themes', array() );
		
		if ( !empty( $aAutoUpdateThemeFiles )
			&& is_array($aAutoUpdateThemeFiles)
			&& in_array( $sItemFile, $aAutoUpdateThemeFiles ) ) {

				return true;
		}
		
		return $infUpdate;
	}
	
	/**
	 * This is a filter method designed to say whether WordPress automatic upgrades should be permitted
	 * if a version control system is detected.
	 * 
	 * @param boolean $infUpdate
	 * @return boolean
	 */
	public function disable_for_vcs( $checkout, $context ) {
		return false;
	}
	
	/**
	 * A filter on whether or not a notification email is send after core upgrades are attempted.
	 * 
	 * @param boolean $infSendEmail
	 * @return boolean
	 */
	public function autoupdate_send_email( $infSendEmail ) {
		return $this->getIsOption( 'enable_upgrade_notification_email', 'Y' );
	}
	
	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * 
	 * @param array $aEmailParams
	 * @return array
	 */
	public function autoupdate_email_override( $aEmailParams ) {
		if ( !empty( $this->m_aOptions['override_email_address'] ) ) {
			$aEmailParams['to'] = $this->m_aOptions['override_email_address'];
		}
		return $aEmailParams;
	}
}

endif;

if ( !class_exists('ICWP_WPSF_AutoUpdatesProcessor') ):
	class ICWP_WPSF_AutoUpdatesProcessor extends ICWP_AutoUpdatesProcessor_V5 { }
endif;
