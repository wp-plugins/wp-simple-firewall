<?php
/*
 * Plugin Name: WordPress Simple Firewall
 * Plugin URI: http://icwp.io/2f
 * Description: A Simple WordPress Firewall
 * Version: 2.6.6
 * Text Domain: wp-simple-firewall
 * Author: iControlWP
 * Author URI: http://icwp.io/2e
 */

/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Firewall" is
 * distributed under the GNU General Public License, Version 2,
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

require_once( dirname(__FILE__).'/icwp-wpsf-main.php' );

class ICWP_Wordpress_Simple_Firewall_Plugin {

	/**
	 * @var string
	 */
	private static $sVersion	= '2.6.6';

	/**
	 * @var string
	 */
	private static $sParentSlug	= 'icwp';

	/**
	 * @var string
	 */
	private static $sPluginSlug	= 'wpsf';

	/**
	 * @var string
	 */
	private static $sHumanName	= 'WordPress Simple Firewall';

	/**
	 * @var string
	 */
	private static $sMenuTitleName	= 'Simple Firewall';

	/**
	 * @var string
	 */
	private static $sTextDomain	= 'wp-simple-firewall';

	/**
	 * @var string
	 */
	private static $sBasePermissions	= 'manage_options';

	/**
	 * @var string
	 */
	private static $sWpmsNetworkAdminOnly	= true;

	/**
	 * @var string
	 */
	private static $sRootFile = '';

	/**
	 * @var string
	 */
	private static $fAutoUpgrade = false;

	/**
	 * @var ICWP_Wordpress_Simple_Firewall_Plugin
	 */
	public static $oInstance;

	/**
	 * @return ICWP_Wordpress_Simple_Firewall_Plugin
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
		if ( empty( self::$sRootFile ) ) {
			self::$sRootFile = __FILE__;
		}
	}

	/**
	 * @return string
	 */
	public function getAdminMenuTitle() {
		return self::$sMenuTitleName;
	}

	/**
	 * @return string
	 */
	public function getAutoUpgrade() {
		return self::$fAutoUpgrade;
	}

	/**
	 * @return string
	 */
	public function getBasePermissions() {
		return self::$sBasePermissions;
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getFullPluginPrefix( $sGlue = '-' ) {
		return sprintf( '%s%s%s', self::$sParentSlug, $sGlue, self::$sPluginSlug );
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getOptionStoragePrefix() {
		return $this->getFullPluginPrefix( '_' ).'_';
	}

	/**
	 * @return string
	 */
	public function getHumanName() {
		return self::$sHumanName;
	}

	/**
	 * @return string
	 */
	public function getIsWpmsNetworkAdminOnly() {
		return self::$sWpmsNetworkAdminOnly;
	}

	/**
	 * @return string
	 */
	public function getParentSlug() {
		return self::$sParentSlug;
	}

	/**
	 * @return string
	 */
	public function getPluginSlug() {
		return self::$sPluginSlug;
	}

	/**
	 * @return string
	 */
	public function getRootFile() {
		return self::$sRootFile;
	}

	/**
	 * @return string
	 */
	public function getTextDomain() {
		return self::$sTextDomain;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return self::$sVersion;
	}
}

$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall( ICWP_Wordpress_Simple_Firewall_Plugin::GetInstance() );