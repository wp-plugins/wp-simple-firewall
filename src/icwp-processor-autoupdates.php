<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
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

if ( !class_exists('ICWP_AutoUpdatesProcessor') ):

class ICWP_AutoUpdatesProcessor extends ICWP_BaseProcessor_WPSF {

	const Slug = 'autoupdates';
	
	/**
	 * @var array
	 */
	protected $m_fDoForceRunAutoUpdates = false;
	
	/**
	 * @var array
	 */
	protected $m_aAutoUpdatePluginFiles;
	
	/**
	 * @var array
	 */
	protected $m_aAutoUpdateThemeFiles;

	public function __construct( $insOptionPrefix = '' ) {
		parent::__construct( $this->constructStorageKey( $insOptionPrefix, self::Slug ) );
	}
	
	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
	}
	
	/**
	 * @param boolean $infDoForceRun
	 */
	public function setForceRunAutoUpdates( $infDoForceRun = false ) {
		$this->m_fDoForceRunAutoUpdates = $infDoForceRun;
	}
	
	/**
	 * @param boolean $infDoForceRun
	 */
	public function getForceRunAutoUpdates( $infDoForceRun = false ) {
		return $this->m_fDoForceRunAutoUpdates;
	}
	
	/**
	 */
	public function run() {
		if ( $this->m_aOptions['autoupdate_core'] == 'core_never' ) {
			add_filter( 'allow_minor_auto_core_updates', '__return_false', 99 );
			add_filter( 'allow_major_auto_core_updates', '__return_false', 99 );
		}
		else if ( $this->m_aOptions['autoupdate_core'] == 'core_minor' ) {
			add_filter( 'allow_minor_auto_core_updates', '__return_true', 99 );
			add_filter( 'allow_major_auto_core_updates', '__return_false', 99 );
		}
		else if ( $this->m_aOptions['autoupdate_core'] == 'core_major' ) {
			add_filter( 'allow_minor_auto_core_updates', '__return_true', 99 );
			add_filter( 'allow_major_auto_core_updates', '__return_true', 99 );
		}

		add_filter( 'auto_update_translation',	array( $this, 'autoupdate_translations' ), 99, 2 );
		add_filter( 'auto_update_plugin',		array( $this, 'autoupdate_plugins' ), 99, 2 );
		add_filter( 'auto_update_theme',		array( $this, 'autoupdate_themes' ), 99, 2 );

		if ( $this->m_aOptions['enable_autoupdate_ignore_vcs'] == 'Y' ) {
			add_filter( 'automatic_updates_is_vcs_checkout', array( $this, 'disable_for_vcs'), 10, 2 );
		}

		if ( $this->m_aOptions['enable_autoupdate_disable_all'] == 'Y' ) {
			add_filter( 'automatic_updater_disabled', '__return_true', 99 );
		}
		
		if ( $this->getForceRunAutoUpdates() ) {
			$this->force_run_autoupdates();
		}
	}

	/**
	 * 
	 */
	public function force_run_autoupdates( ) {
		$lock_name = 'auto_updater.lock'; //ref: /wp-admin/includes/class-wp-upgrader.php
		delete_option( $lock_name );
		if ( !defined('DOING_CRON') ) {
			define( 'DOING_CRON', true ); // this prevent WP from disabling the plugin pre-upgrade
		}
		wp_maybe_auto_update();
 		wp_redirect( get_admin_url( null, 'update-core.php') );
 		exit();
	}
	
	public function autoupdate_translations( $infUpdate, $insSlug ) {
		if ( $this->m_aOptions['enable_autoupdate_translations'] == 'Y' ) {
			return true;
		}
		return $infUpdate;
	}

	public function autoupdate_plugins( $infUpdate, $insPluginSlug ) {
		
		if ( strpos( $insPluginSlug, 'icwp-wpsf.php') !== false ) {
			return $this->m_aOptions['autoupdate_plugin_wpsf'] == 'Y';
		}

		$aAutoUpdatePluginFiles = array();
		$aAutoUpdatePluginFiles = apply_filters( 'icwp_wpsf_autoupdate_plugins', $aAutoUpdatePluginFiles );
		
		if ( !empty( $aAutoUpdatePluginFiles ) && is_array($aAutoUpdatePluginFiles) ) {
			if ( in_array( $insPluginSlug, $aAutoUpdatePluginFiles ) ) {
				return true;
			}
		}
		if ( $this->m_aOptions['enable_autoupdate_plugins'] == 'Y' ) {
			return true;
		}
		return $infUpdate;
	}
	
	public function autoupdate_themes( $infUpdate, $insThemeSlug ) {
		
		$aAutoUpdateThemeFiles = array();
		$aAutoUpdateThemeFiles = apply_filters( 'icwp_wpsf_autoupdate_themes', $aAutoUpdateThemeFiles );
		
		if ( !empty( $aAutoUpdateThemeFiles ) && is_array($aAutoUpdateThemeFiles) ) {
			if ( in_array( $insThemeSlug, $aAutoUpdateThemeFiles ) ) {
				return true;
			}
		}
		if ( $this->m_aOptions['enable_autoupdate_themes'] == 'Y' ) {
			return true;
		}
		
		return $infUpdate;
	}
	
	public function disable_for_vcs( $checkout, $context ) {
		return false;
	}
}

endif;