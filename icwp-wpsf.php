<?php
/*
 * Plugin Name: WordPress Simple Firewall
 * Plugin URI: http://icwp.io/2f
 * Description: A Simple WordPress Firewall
 * Version: 3.5.3
 * Text Domain: wp-simple-firewall
 * Author: iControlWP
 * Author URI: http://icwp.io/2e
 */

/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Firewall" is distributed under the GNU General Public License, Version 2,
 * June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110, USA
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

if ( !defined('ICWP_DS') ) {
	define( 'ICWP_DS', DIRECTORY_SEPARATOR );
}

require_once( dirname(__FILE__).ICWP_DS.'src'.ICWP_DS.'icwp-foundation.php' );

class ICWP_Wordpress_Simple_Firewall_Plugin extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Spec
	 */
	private static $oPluginSpec;

	/**
	 * @const string
	 */
	const ViewDir				= 'views';

	/**
	 * @const string
	 */
	const SrcDir				= 'src';

	/**
	 * @var string
	 */
	protected static $fLoggingEnabled;

	/**
	 * @var string
	 */
	private static $sRootFile = '';

	/**
	 * @var string
	 */
	private $sPluginUrl;

	/**
	 * @var string
	 */
	private $sPluginBaseFile;

	/**
	 * @var ICWP_Wordpress_Simple_Firewall_Plugin
	 */
	public static $oInstance;

	/**
	 * @param ICWP_WPSF_Spec $oPluginSpec
	 * @return ICWP_Wordpress_Simple_Firewall_Plugin
	 */
	public static function GetInstance( $oPluginSpec ) {
		if ( !isset( self::$oInstance ) ) {
			self::$oInstance = new self( $oPluginSpec );
		}
		return self::$oInstance;
	}

	/**
	 * @param ICWP_WPSF_Spec $oPluginSpec
	 */
	private function __construct( $oPluginSpec ) {
		if ( empty( self::$oPluginSpec ) ) {
			self::$sRootFile = __FILE__;
			self::$oPluginSpec = $oPluginSpec;
			add_action( 'plugins_loaded',			array( $this, 'onWpPluginsLoaded' ) );
			add_action( 'shutdown',					array( $this, 'onWpShutdown' ) );
		}
	}

	/**
	 * Hooked to 'plugins_loaded'
	 */
	public function onWpPluginsLoaded() {
		if ( $this->getIsValidAdminArea() ) {
			add_action( 'admin_notices',			array( $this, 'onWpAdminNotices' ) );
			add_action( 'network_admin_notices',	array( $this, 'onWpAdminNotices' ) );
			add_filter( 'all_plugins', array( $this, 'filter_hidePluginFromTableList' ) );
			add_filter( 'site_transient_update_plugins', array( $this, 'filter_hidePluginUpdatesFromUI' ) );
			add_action( 'in_plugin_update_message-'.$this->getPluginBaseFile(), array( $this, 'onWpPluginUpdateMessage' ) );
		}
		$this->doPluginFormSubmit();
		$this->doLoadTextDomain();
	}

	/**
	 */
	public function onWpAdminNotices() {
		// Do we have admin priviledges?
		if ( !$this->getIsValidAdminArea() ) {
			return true;
		}
		$aAdminNotices = apply_filters( $this->doPluginPrefix( 'admin_notices' ), array() );
		if ( empty( $aAdminNotices ) || !is_array( $aAdminNotices ) ) {
			return;
		}
		foreach( $aAdminNotices as $sAdminNotice ) {
			echo $sAdminNotice;
		}
	}

	/**
	 * Displays a message in the plugins listing when a plugin has an update available.
	 */
	public function onWpPluginUpdateMessage() {
		$sMessage = apply_filters( $this->doPluginPrefix( 'plugin_update_message' ), '' );
		if ( empty( $sMessage ) ) {
			$sMessage = '';
		}
		else {
			$sMessage = sprintf(
				'<div class="%s plugin_update_message">%s</div>',
				$this->getPluginPrefix(),
				$sMessage
			);
		}
		echo $sMessage;
	}

	/**
	 * Hooked to 'shutdown'
	 */
	public function onWpShutdown() {
		do_action( $this->doPluginPrefix( 'plugin_shutdown' ) );
	}

	/**
	 * Added to a WordPress filter ('all_plugins') which will remove this particular plugin from the
	 * list of all plugins based on the "plugin file" name.
	 *
	 * @param array $aPlugins
	 * @return array
	 */
	public function filter_hidePluginFromTableList( $aPlugins ) {

		$fHide = apply_filters( $this->doPluginPrefix( 'hide_plugin' ), false );

		if ( !$fHide ) {
			return $aPlugins;
		}

		$sPluginBaseFileName = $this->getPluginBaseFile();
		if ( isset( $aPlugins[$sPluginBaseFileName] ) ) {
			unset( $aPlugins[$sPluginBaseFileName] );
		}
		return $aPlugins;
	}

	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates
	 * from the WordPress Admin UI.
	 *
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 *
	 * @uses $this->fHeadless if the plugin is headless, it is hidden
	 * @param StdClass $oPlugins
	 * @return StdClass
	 */
	public function filter_hidePluginUpdatesFromUI( $oPlugins ) {

		if ( $this->loadWpFunctionsProcessor()->getIsCron() ) {
			return $oPlugins;
		}

		if ( ! apply_filters( $this->doPluginPrefix( 'hide_plugin_updates' ), false ) ) {
			return $oPlugins;
		}

		if ( isset( $oPlugins->response[ $this->getPluginBaseFile() ] ) ) {
			unset( $oPlugins->response[ $this->getPluginBaseFile() ] );
		}
		return $oPlugins;
	}

	/**
	 * @return string
	 */
	public function getAdminMenuTitle() {
		return self::$oPluginSpec->getAdminMenuTitle();;
	}

	/**
	 * @return string
	 */
	public function getBasePermissions() {
		return self::$oPluginSpec->getBasePermissions();
	}

	/**
	 * @param bool $fCheckUserPermissions
	 * @return bool
	 */
	public function getIsValidAdminArea( $fCheckUserPermissions = true ) {
		if ( $fCheckUserPermissions && !current_user_can( $this->getBasePermissions() ) ) {
			return false;
		}

		$oWp = $this->loadWpFunctionsProcessor();
		if ( !$oWp->isMultisite() && is_admin() ) {
			return true;
		}
		else if ( $oWp->isMultisite() && $this->getIsWpmsNetworkAdminOnly() && is_network_admin() ) {
			return true;
		}
		return false;
	}

	/**
	 */
	protected function doLoadTextDomain() {
		return load_plugin_textdomain(
			$this->getTextDomain(),
			false,
			plugin_basename( $this->getPath_Languages() )
		);
	}

	protected function doPluginFormSubmit() {
		if ( !$this->getIsPluginFormSubmit() ) {
			return false;
		}

		// do all the plugin feature/options saving
		do_action( $this->doPluginPrefix( 'form_submit' ) );

		if ( $this->getIsPage_PluginAdmin() ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->doRedirect( $oWp->getUrl_CurrentAdminPage() );
			return true;
		}
	}

	/**
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function doPluginPrefix( $sSuffix = '', $sGlue = '-' ) {
		$sPrefix = $this->getPluginPrefix( $sGlue );

		if ( $sSuffix == $sPrefix || strpos( $sSuffix, $sPrefix.$sGlue ) === 0 ) { //it already has the full prefix
			return $sSuffix;
		}

		return sprintf( '%s%s%s', $sPrefix, empty($sSuffix)? '' : $sGlue, empty($sSuffix)? '' : $sSuffix );
	}

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	public function doPluginOptionPrefix( $sSuffix = '' ) {
		return $this->doPluginPrefix( $sSuffix, '_' );
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getOptionStoragePrefix() {
		return $this->getPluginPrefix( '_' ).'_';
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getPluginPrefix( $sGlue = '-' ) {
		return sprintf( '%s%s%s', $this->getParentSlug(), $sGlue, $this->getPluginSlug() );
	}

	/**
	 * @return string
	 */
	public function getHumanName() {
		return self::$oPluginSpec->getHumanName();
	}

	/**
	 * @return string
	 */
	public function getIsLoggingEnabled() {
		return self::$oPluginSpec->getIsLoggingEnabled();
	}

	/**
	 * @return bool
	 */
	protected function getIsPage_PluginAdmin() {
		$oWp = $this->loadWpFunctionsProcessor();
		return ( strpos( $oWp->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
	}

	/**
	 * @return bool
	 */
	protected function getIsPluginFormSubmit() {
		if ( empty( $_POST ) && empty( $_GET ) ) {
			return false;
		}

		$aFormSubmitOptions = array(
			$this->doPluginOptionPrefix( 'plugin_form_submit' ),
			'icwp_link_action'
		);

		$oDp = $this->loadDataProcessor();
		foreach( $aFormSubmitOptions as $sOption ) {
			if ( !is_null( $oDp->FetchRequest( $sOption ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getIsWpmsNetworkAdminOnly() {
		return self::$oPluginSpec->getIsWpmsNetworkAdminOnly();
	}

	/**
	 * @return string
	 */
	public function getParentSlug() {
		return self::$oPluginSpec->getParentSlug();
	}

	/**
	 * This is the path to the main plugin file relative to the WordPress plugins directory.
	 *
	 * @return string
	 */
	public function getPluginBaseFile() {
		if ( !isset( $this->sPluginBaseFile ) ) {
			$this->sPluginBaseFile = plugin_basename( $this->getRootFile() );
		}
		return $this->sPluginBaseFile;
	}

	/**
	 * @return string
	 */
	public function getPluginSlug() {
		return self::$oPluginSpec->getPluginSlug();
	}

	/**
	 * @param string $sPath
	 * @return string
	 */
	public function getPluginUrl( $sPath = '' ) {
		if ( empty( $this->sPluginUrl ) ) {
			$this->sPluginUrl = plugins_url( '/', $this->getRootFile() );
		}
		return $this->sPluginUrl.$sPath;
	}

	/**
	 * @param string $sCss
	 * @return string
	 */
	public function getPluginUrl_Css( $sCss ) {
		return $this->getPluginUrl( 'resources/css/'.$sCss );
	}

	/**
	 * @param string $sImage
	 * @return string
	 */
	public function getPluginUrl_Image( $sImage ) {
		return $this->getPluginUrl( 'resources/images/'.$sImage );
	}

	/**
	 * @param string $sJs
	 * @return string
	 */
	public function getPluginUrl_Js( $sJs ) {
		return $this->getPluginUrl( 'resources/js/'.$sJs );
	}

	/**
	 * @param string $sFeature
	 * @return string
	 */
	public function getPluginUrl_AdminPage( $sFeature = '' ) {
		return network_admin_url( sprintf( 'admin.php?page=%s', $this->doPluginPrefix( $sFeature ) ) );
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Languages() {
		return $this->getRootDir().'languages'.ICWP_DS;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getRootDir() {
		return dirname( $this->getRootFile() ).ICWP_DS;
	}

	/**
	 * @return string
	 */
	public function getRootFile() {
		return self::$sRootFile;
	}

	/**
	 * Get the directory for the plugin source files with the trailing slash
	 *
	 * @param string $sSourceFile
	 * @return string
	 */
	public function getSourceDir( $sSourceFile = '' ) {
		return $this->getRootDir().self::SrcDir.ICWP_DS.$sSourceFile;
	}

	/**
	 * @return string
	 */
	public function getTextDomain() {
		return self::$oPluginSpec->getTextDomain();
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return self::$oPluginSpec->getVersion();
	}

	/**
	 * get the directory for the plugin view with the trailing slash
	 *
	 * @return string
	 */
	public function getViewDir() {
		return $this->getRootDir().self::ViewDir.ICWP_DS;
	}

	/**
	 * Retrieve the full path to the plugin view
	 *
	 * @param string $sView
	 * @return string
	 */
	public function getViewPath( $sView ) {
		return $this->getViewDir().$sView.'.php';
	}

	/**
	 * @param string $sSnippet
	 * @return string
	 */
	public function getViewSnippet( $sSnippet ) {
		return $this->getViewDir().'snippets'.ICWP_DS.$sSnippet.'.php';
	}
}

require_once( 'icwp-wpsf-spec.php' );
require_once( 'icwp-wpsf-main.php' );
$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall( ICWP_Wordpress_Simple_Firewall_Plugin::GetInstance( ICWP_WPSF_Spec::GetInstance() ) );