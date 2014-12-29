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

require_once( dirname(__FILE__).ICWP_DS.'icwp-processor-base.php' );

if ( !class_exists('ICWP_WPSF_AutoupdatesProcessor_V5') ):

	class ICWP_WPSF_AutoupdatesProcessor_V5 extends ICWP_WPSF_Processor_Base {

		const FilterPriority = 1001;

		/**
		 * @var boolean
		 */
		protected $bDoForceRunAutoupdates = false;

		/**
		 * @param ICWP_WPSF_FeatureHandler_Autoupdates $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_Autoupdates $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions );
		}

		/**
		 * @param boolean $bDoForceRun
		 */
		public function setForceRunAutoupdates( $bDoForceRun ) {
			$this->bDoForceRunAutoupdates = $bDoForceRun;
		}

		/**
		 * @return boolean
		 */
		public function getIfForceRunAutoupdates() {
			return apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'force_autoupdate' ), $this->bDoForceRunAutoupdates );
		}

		/**
		 */
		public function run() {

			$oDp = $this->loadDataProcessor();
			if ( $oDp->FetchGet( 'forcerun' ) == 1 ) {
				$this->setForceRunAutoupdates( true );
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

			add_action( 'wp_loaded', array( $this, 'force_run_autoupdates' ) );
			add_filter( 'plugin_row_meta', array( $this, 'fDetectAutomaticUpdateSettings' ), self::FilterPriority, 2 );
		}

		/**
		 * Will force-run the WordPress automatic updates process and then redirect to the updates screen.
		 *
		 * @return bool
		 */
		public function force_run_autoupdates() {

			if ( !$this->getIfForceRunAutoupdates() ) {
				return true;
			}
			$this->doStatIncrement( 'autoupdates.forcerun' );
			return $this->loadWpFunctionsProcessor()->doForceRunAutomaticUpdates();
		}

		/**
		 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
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
			return $bUpdate;
		}

		/**
		 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
		 * @return boolean
		 */
		public function autoupdate_core_minor( $bUpdate ) {
			if ( $this->getIsOption('autoupdate_core', 'core_never') ) {
				$this->doStatIncrement( 'autoupdates.core.minor.blocked' );
				return false;
			}
			else if ( $this->getIsOption('autoupdate_core', 'core_minor') ) {
				$this->doStatIncrement( 'autoupdates.core.minor.allowed' );
				return true;
			}
			return $bUpdate;
		}

		/**
		 * This is a filter method designed to say whether a WordPress translations upgrades should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
		 * @param string $sSlug
		 * @return boolean
		 */
		public function autoupdate_translations( $bUpdate, $sSlug ) {
			if ( $this->getIsOption( 'enable_autoupdate_translations', 'Y' ) ) {
				return true;
			}
			return $bUpdate;
		}

		/**
		 * This is a filter method designed to say whether WordPress plugin upgrades should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bDoAutoUpdate
		 * @param StdClass|string $mItem
		 *
		 * @return boolean
		 */
		public function autoupdate_plugins( $bDoAutoUpdate, $mItem ) {

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
				return $bDoAutoUpdate;
			}

			// If it's this plugin and autoupdate this plugin is set...
			if ( $sItemFile === $this->getFeatureOptions()->getPluginBaseFile() ) {
				if ( $this->getIsOption('autoupdate_plugin_self', 'Y') ) {
					$this->doStatIncrement( 'autoupdates.plugins.self' );
					$bDoAutoUpdate = true;
				}
				else {
					$bDoAutoUpdate = false;
				}
			}

			$aAutoupdateFiles = apply_filters( 'icwp_wpsf_autoupdate_plugins', array() );
			if ( !empty( $aAutoupdateFiles ) && is_array( $aAutoupdateFiles ) && in_array( $sItemFile, $aAutoupdateFiles ) ) {
				$bDoAutoUpdate = true;
			}
			return $bDoAutoUpdate;
		}

		/**
		 * This is a filter method designed to say whether WordPress theme upgrades should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bDoAutoUpdate
		 * @param stdClass|string $mItem
		 *
		 * @return boolean
		 */
		public function autoupdate_themes( $bDoAutoUpdate, $mItem ) {

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
				return $bDoAutoUpdate;
			}

			$aAutoupdateFiles = apply_filters( 'icwp_wpsf_autoupdate_themes', array() );
			if ( !empty( $aAutoupdateFiles ) && is_array( $aAutoupdateFiles ) && in_array( $sItemFile, $aAutoupdateFiles ) ) {
				$bDoAutoUpdate = true;
			}
			return $bDoAutoUpdate;
		}

		/**
		 * This is a filter method designed to say whether WordPress automatic upgrades should be permitted
		 * if a version control system is detected.
		 *
		 * @param $checkout
		 * @param $context
		 * @return boolean
		 */
		public function disable_for_vcs( $checkout, $context ) {
			return false;
		}

		/**
		 * A filter on whether or not a notification email is send after core upgrades are attempted.
		 *
		 * @param boolean $bSendEmail
		 * @return boolean
		 */
		public function autoupdate_send_email( $bSendEmail ) {
			return $this->getIsOption( 'enable_upgrade_notification_email', 'Y' );
		}

		/**
		 * A filter on the target email address to which to send upgrade notification emails.
		 *
		 * @param array $aEmailParams
		 * @return array
		 */
		public function autoupdate_email_override( $aEmailParams ) {
			$sOverride = $this->getOption( 'override_email_address', '' );
			if ( !empty( $sOverride ) && is_email( $sOverride ) ) {
				$aEmailParams['to'] = $sOverride;
			}
			return $aEmailParams;
		}

		/**
		 * @filter
		 * @param array $aPluginMeta
		 * @param string $sPluginBaseFileName
		 * @return array
		 */
		public function fDetectAutomaticUpdateSettings( $aPluginMeta, $sPluginBaseFileName ) {

			// first we prevent collision between iControlWP <-> Simple Firewall by not duplicating icons
			foreach( $aPluginMeta as $sMetaItem ) {
				if ( strpos( $sMetaItem, 'icwp-pluginautoupdateicon' ) !== false ) {
					return $aPluginMeta;
				}
			}

			$bUpdate = $this->loadWpFunctionsProcessor()->getIsPluginAutomaticallyUpdated( $sPluginBaseFileName );
			$sUpdateString = sprintf(
				'<span title="%s" class="icwp-pluginautoupdateicon dashicons dashicons-%s"></span>',
				$bUpdate ? 'WordPress will automatically update this plugin as updates become available' : 'Will need to be manually updated',
				$bUpdate ? 'update' : 'hammer'
			);
			array_unshift( $aPluginMeta, sprintf( '%s', $sUpdateString ) );
			return $aPluginMeta;
		}

		/**
		 * Removes all filters that have been added from auto-update related WordPress filters
		 */
		protected function removeAllAutoupdateFilters() {
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
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Autoupdates') ):
	class ICWP_WPSF_Processor_Autoupdates extends ICWP_WPSF_AutoupdatesProcessor_V5 { }
endif;
