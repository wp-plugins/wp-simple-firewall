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

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_WPSF_OptionsHandler_UserManagement') ):

class ICWP_OptionsHandler_UserManagement extends ICWP_OptionsHandler_Base_Wpsf {
	
	/**
	 * @var ICWP_WPSF_Processor_UserManagement
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('User Management');
		$this->sFeatureSlug = 'user_management';
		parent::__construct( $oPluginVo );
	}

	/**
	 * @return ICWP_WPSF_OptionsHandler_UserManagement|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( dirname(__FILE__).'/icwp-processor-usermanagement.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_UserManagement( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 */
	public function displayFeatureConfigPage( ) {

		if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_view' ), true ) ) {
			$this->displayViewAccessRestrictedPage();
			return;
		}

		$aData = array(
			'aActiveSessions'		=> $this->getIsMainFeatureEnabled()? $this->oFeatureProcessor->getActiveUserSessionRecords() : array(),
			'aFailedSessions'		=> $this->getIsMainFeatureEnabled()? $this->oFeatureProcessor->getPendingOrFailedUserSessionRecordsSince() : array()
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( $aData );
	}
	
	public function doPrePluginOptionsSave() { }

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {
		$aOptionsBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('User Accounts Management') ),
			'section_options' => array(
				array(
					'enable_user_management',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable User Accounts Management' ),
					_wpsf__( 'Enable (or Disable) The User Accounts Management Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('User Accounts Management') ),
				)
			),
		);

		$aSessions = array(
			'section_title' => _wpsf__( 'User Session Management' ),
			'section_options' => array(
				array(
					'session_timeout_interval',
					'',
					'2',
					'integer',
					_wpsf__( 'Session Timeout' ),
					_wpsf__( 'Specify How Many Days After Login To Automatically Force Re-Login' ),
					sprintf( _wpsf__( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				),
				array(
					'session_idle_timeout_interval',
					'',
					'0',
					'integer',
					_wpsf__( 'Idle Timeout' ),
					_wpsf__( 'Specify How Many Hours After Inactivity To Automatically Logout User' ),
					_wpsf__( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.' )
				),
				array(
					'session_lock_location',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Lock To Location' ),
					_wpsf__( 'Locks A User Session To IP address' ),
					_wpsf__( 'Restricts login to any account to a location as determined by IP address.' )
				)
			)
		);

		$aOptionsDefinitions = array(
			$aOptionsBase,
			$aSessions
		);
		return $aOptionsDefinitions;
	}

	/**
	 * @return array
	 */
	protected function getNonUiOptions() {
		$aNonUiOptions = array();
		return $aNonUiOptions;
	}
}

endif;