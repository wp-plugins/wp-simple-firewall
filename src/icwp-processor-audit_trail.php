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

require_once( dirname(__FILE__).'/icwp-processor-base.php' );

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_V1') ):

	class ICWP_WPSF_Processor_AuditTrail_V1 extends ICWP_WPSF_BaseDbProcessor {

		/**
		 * @var ICWP_WPSF_FeatureHandler_AuditTrail
		 */
		protected $oFeatureOptions;

		/**
		 * @var ICWP_WPSF_AuditTrail_Entries
		 */
		protected $oAuditEntries;

		/**
		 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions, $oFeatureOptions->getAuditTrailTableName() );
			add_action( $this->oFeatureOptions->doPluginPrefix( 'plugin_shutdown' ), array( $this, 'commitAuditTrial' ) );
		}

		/**
		 */
		public function run() {

			if ( $this->getIsOption( 'enable_audit_context_users', 'Y' ) ) {
				add_action( 'wp_login_failed', array( $this, 'auditUserLoginFail' ) );
				add_action( 'wp_login', array( $this, 'auditUserLoginSuccess' ) );
			}

		}

		/**
		 * @return array|bool
		 */
		public function getAllAuditEntries() {
			return $this->selectAllFromTable();
		}

		/**
		 * @param string $sUsername
		 * @return bool
		 */
		public function auditUserLoginSuccess( $sUsername ) {

			if ( empty( $sUsername ) ) {
				return false;
			}
			$oAuditTrail = $this->getAuditTrailEntries();

			$oDp = $this->loadDataProcessor();
			$oAuditTrail->add(
				$oDp->GetRequestTime(),
				$sUsername,
				'user',
				'login_success',
				1,
				sprintf( _wpsf__( 'Attempted user login by "%s" was successful.' ), $sUsername )
			);
		}

		/**
		 * @param string $sUsername
		 * @return bool
		 */
		public function auditUserLoginFail( $sUsername ) {

			if ( empty( $sUsername ) ) {
				return false;
			}
			$oAuditTrail = $this->getAuditTrailEntries();

			$oDp = $this->loadDataProcessor();
			$oAuditTrail->add(
				$oDp->GetRequestTime(),
				$sUsername,
				'user',
				'login_failure',
				2,
				sprintf( _wpsf__( 'Attempted user login by "%s" was failed.' ), $sUsername )
			);
		}

		/**
		 */
		public function commitAuditTrial() {
			$aEntries = $this->getAuditTrailEntries()->getAuditTrailEntries( true );
			if ( empty( $aEntries ) || !is_array( $aEntries )) {
				return;
			}

			foreach( $aEntries as $aEntry ) {
				$this->insertIntoTable( $aEntry );
			}
		}

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			if ( !isset( $this->oAuditEntries ) ) {
				$this->oAuditEntries = new ICWP_WPSF_AuditTrail_Entries();
			}
			return $this->oAuditEntries;
		}

		/**
		 * @return string
		 */
		protected function getCreateTableSql() {
			$sSqlTables = "
				CREATE TABLE IF NOT EXISTS `%s` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`wp_username` VARCHAR(255) NOT NULL DEFAULT 'none',
				`context` VARCHAR(25) NOT NULL DEFAULT 'none',
				`event` VARCHAR(25) NOT NULL DEFAULT 'none',
				`category` INT(3) NOT NULL DEFAULT '0',
				`message` TEXT,
				`created_at` INT(15) NOT NULL DEFAULT '0',
				`deleted_at` INT(15) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

			return sprintf( $sSqlTables, $this->getTableName() );
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail') ):
	class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_Processor_AuditTrail_V1 { }
endif;

class ICWP_WPSF_AuditTrail_Entries {

	/**
	 * @var array
	 */
	protected $aEntries;

	public function add( $nDate, $sWpUsername, $sContext, $sEvent, $nCategory, $sMessage = '' ) {
		$aNewEntry = array(
			'created_at' => $nDate,
			'wp_username' => $sWpUsername,
			'context' => $sContext,
			'event' => $sEvent,
			'category' => $nCategory,
			'message' => $sMessage,
		);
		$aEntries = $this->getAuditTrailEntries();
		$aEntries[] = $aNewEntry;
		$this->aEntries = $aEntries;
	}

	/**
	 * For use inside the object
	 *
	 * @return array
	 */
	protected function & getEntries() {
		if ( !isset( $this->aEntries ) ) {
			$this->aEntries = array();
		}
		return $this->aEntries;
	}

	/**
	 * @param boolean $fFlush
	 * @return array
	 */
	public function getAuditTrailEntries( $fFlush = false ) {
		if ( !isset( $this->aEntries ) ) {
			$this->aEntries = array();
		}
		$aEntries = $this->aEntries;
		if ( $fFlush ) {
			$this->aEntries = array();
		}
		return $aEntries;
	}
}