<?php
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

if ( !function_exists( '_wpsf_e' ) ) {
	function _wpsf_e( $insStr ) {
		_e( $insStr, 'wp-simple-firewall' );
	}
}
if ( !function_exists( '_wpsf__' ) ) {
	function _wpsf__( $insStr ) {
		return __( $insStr, 'wp-simple-firewall' );
	}
}

require_once(dirname(__FILE__).ICWP_DS.'src'.ICWP_DS.'icwp-foundation.php');
class ICWP_WPSF_Plugin_Controller extends ICWP_WPSF_Foundation {

	/**
	 * @var array
	 */
	private static $aPluginSpec;

	/**
	 * @var string
	 */
	private static $sRootFile;

	/**
	 * @var ICWP_WPSF_Plugin_Controller
	 */
	public static $oInstance;

	/**
	 * @var string
	 */
	private $sPluginUrl;

	/**
	 * @var string
	 */
	private $sPluginBaseFile;

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	public static function GetInstance() {
		if ( !isset( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 */
	private function __construct() {
		if ( empty( self::$aPluginSpec ) ) {
			self::$aPluginSpec = $this->readPluginConfiguration();
			add_action( 'plugins_loaded',			array( $this, 'onWpPluginsLoaded' ) );
			add_action( 'shutdown',					array( $this, 'onWpShutdown' ) );
			$this->registerActivationHooks();
		}
	}

	/**
	 * Registers the plugins activation, deactivate and uninstall hooks.
	 */
	protected function registerActivationHooks() {
		register_activation_hook( $this->getRootFile(), array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( $this->getRootFile(), array( $this, 'onWpDeactivatePlugin' ) );
		//	register_uninstall_hook( $this->oPluginVo->getRootFile(), array( $this, 'onWpUninstallPlugin' ) );
	}

	// TODO: this is dependent on a specific plugin option variable - need to move it into the handlers
	public function onWpDeactivatePlugin() {
		if ( $this->loadCorePluginFeatureHandler()->getOptIs( 'delete_on_deactivate', 'Y' ) && current_user_can( $this->getBasePermissions() ) ) {
			do_action( $this->doPluginPrefix( 'delete_plugin' ) );
		}
	}

	public function onWpActivatePlugin() {
		$this->loadAllFeatures( true, true );
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
	 */
	protected function doLoadTextDomain() {
		return load_plugin_textdomain(
			$this->getTextDomain(),
			false,
			plugin_basename( $this->getPath_Languages() )
		);
	}

	/**
	 * @return bool
	 */
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
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getProperty( $sKey ) {
		return isset( self::$aPluginSpec['properties'][$sKey] ) ? self::$aPluginSpec['properties'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPath( $sKey ) {
		return isset( self::$aPluginSpec['paths'][$sKey] ) ? self::$aPluginSpec['paths'][$sKey] : null;
	}

	/**
	 * @return string
	 */
	public function getAdminMenuTitle() {
		return $this->getProperty( 'menu_title' );
	}

	/**
	 * @return string
	 */
	public function getBasePermissions() {
		return $this->getProperty( 'base_permissions' );
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
		return $this->getProperty( 'human_name' );
	}

	/**
	 * @return string
	 */
	public function getIsLoggingEnabled() {
		return $this->getProperty( 'logging_enabled' );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginAdmin() {
		$oWp = $this->loadWpFunctionsProcessor();
		return ( strpos( $oWp->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginMainDashboard() {
		$oWp = $this->loadWpFunctionsProcessor();
		return ( $oWp->getCurrentWpAdminPage() == $this->getPluginPrefix() );
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
	 * @return boolean
	 */
	public function getIsWpmsNetworkAdminOnly() {
		return $this->getProperty( 'wpms_network_admin_only' );
	}

	/**
	 * @return string
	 */
	public function getParentSlug() {
		return $this->getProperty( 'slug_parent' );
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
		return $this->getProperty( 'slug_plugin' );
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
	public function getPath_Assets() {
		return $this->getRootDir().$this->getPath( 'assets' ).ICWP_DS;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Languages() {
		return $this->getRootDir().$this->getPath( 'languages' ).ICWP_DS;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Source() {
		return $this->getRootDir().$this->getPath( 'source' ).ICWP_DS;
	}

	/**
	 * Get the directory for the plugin source files with the trailing slash
	 *
	 * @param string $sSourceFile
	 * @return string
	 */
	public function getPath_SourceFile( $sSourceFile = '' ) {
		return $this->getPath_Source().$sSourceFile;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Views() {
		return $this->getRootDir().$this->getPath( 'views' ).ICWP_DS;
	}

	/**
	 * Retrieve the full path to the plugin view
	 *
	 * @param string $sView
	 * @return string
	 */
	public function getPath_ViewsFile( $sView ) {
		return $this->getPath_Views().$sView.'.php';
	}

	/**
	 * @param string $sSnippet
	 * @return string
	 */
	public function getPath_ViewsSnippet( $sSnippet ) {
		return $this->getPath_Views().'snippets'.ICWP_DS.$sSnippet.'.php';
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
		if ( !isset( self::$sRootFile ) ) {
			self::$sRootFile = __FILE__;
		}
		return self::$sRootFile;
	}

	/**
	 * @return string
	 */
	public function getTextDomain() {
		return $this->getProperty( 'text_domain' );
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->getProperty( 'version' );
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Plugin
	 */
	public function loadCorePluginFeatureHandler() {
		if ( !isset( $this->oFeatureHandlerPlugin ) ) {
			$this->loadFeatureHandler( array( 'slug' => 'plugin' ) );
		}
		return $this->oFeatureHandlerPlugin;
	}

	/**
	 * @param bool $fRecreate
	 * @param bool $fFullBuild
	 * @return bool
	 */
	public function loadAllFeatures( $fRecreate = false, $fFullBuild = false ) {

		$oMainPluginFeature = $this->loadCorePluginFeatureHandler();
		$aPluginFeatures = $oMainPluginFeature->getActivePluginFeatures();

		$fSuccess = true;
		foreach( $aPluginFeatures as $sSlug => $aFeatureProperties ) {
			try {
				$this->loadFeatureHandler( $aFeatureProperties, $fRecreate, $fFullBuild );
				$fSuccess = true;
			}
			catch( Exception $oE ) {
				wp_die( $oE->getMessage() );
			}
		}
		return $fSuccess;
	}

	/**
	 * @param array $aFeatureProperties
	 * @param bool $fRecreate
	 * @param bool $fFullBuild
	 * @return mixed
	 * @throws Exception
	 */
	public function loadFeatureHandler( $aFeatureProperties, $fRecreate = false, $fFullBuild = false ) {

		$sFeatureSlug = $aFeatureProperties['slug'];

		$sFeatureName = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $sFeatureSlug ) ) );
		$sOptionsVarName = sprintf( 'oFeatureHandler%s', $sFeatureName ); // e.g. oFeatureHandlerPlugin

		if ( isset( $this->{$sOptionsVarName} ) ) {
			return $this->{$sOptionsVarName};
		}

		// todo: remove ICWP and WPSF dependencies
		$sSourceFile = $this->getPath_SourceFile(
			sprintf(
				'%s-optionshandler-%s.php',
				$this->getParentSlug(),
				$sFeatureSlug
			)
		); // e.g. icwp-optionshandler-plugin.php
		$sClassName = sprintf(
			'%s_%s_FeatureHandler_%s',
			strtoupper( $this->getParentSlug() ),
			strtoupper( $this->getPluginSlug() ),
			$sFeatureName
		); // e.g. ICWP_WPSF_FeatureHandler_Plugin

		require_once( $sSourceFile );
		if ( $fRecreate || !isset( $this->{$sOptionsVarName} ) ) {
			$this->{$sOptionsVarName} = new $sClassName( $this, $aFeatureProperties );
		}
		if ( $fFullBuild ) {
			$this->{$sOptionsVarName}->buildOptions();
		}
		return $this->{$sOptionsVarName};
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readPluginConfiguration() {
		$oFs = $this->loadFileSystemProcessor();

		$aConfig = array();
		$sConfigFile = $this->getRootDir().'plugin-spec.txt';
		$sContents = $oFs->getFileContent( $sConfigFile );
		if ( !empty( $sContents ) ) {
			$oYaml = $this->loadYamlProcessor();
			$aConfig = $oYaml->parseYamlString( $sContents );
			if ( is_null( $aConfig ) ) {
				throw new Exception( 'YAML parser could not load to process the plugin spec configuration.' );
			}
		}
		return $aConfig;
	}
}