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

require_once( dirname(__FILE__).'/icwp-basedb-processor.php' );

if ( !class_exists('ICWP_CommentsProcessor') ):

class ICWP_CommentsProcessor extends ICWP_BaseDbProcessor_WPSF {
	
	const TableName					= 'comments_filter';
	const DefaultCommentCooldown	= 30; //seconds.
	const DefaultCommentExpire		= 600; //seconds.
	
	/**
	 * @var string
	 */
	static protected $sModeFile_LoginThrottled;
	
	/**
	 * The unique comment token assigned to this page
	 * @var integer
	 */
	protected $m_sUniqueToken;
	
	/**
	 * The unique comment token assigned to this page
	 * @var integer
	 */
	protected $m_sUniqueFormId;
	
	/**
	 * The length of time that must pass between a page being loaded and comment being posted.
	 * @var integer
	 */
	protected $m_nCommentCooldown;
	
	/**
	 * @var ICWP_OptionsHandler_CommentsFilter
	 */
	protected $m_oOptions;
	
	/**
	 * The maxium length of time that comment token may last and be used.
	 * @var integer
	 */
	protected $m_nCommentTokenExpire;
	
	protected $m_nLastLoginTime;
	protected $m_sSecretKey;
	
	protected $m_sGaspKey;
	
	/**
	 * Flag as to whether Two Factor Authentication will be by-pass when sending the verification
	 * email fails.
	 * 
	 * @var boolean
	 */
	protected $m_fAllowTwoFactorByPass;
	
	public function __construct() {
		parent::__construct( self::TableName );
		$this->createTable();
		$this->reset();
	}

	/**
	 * Ensure that when we save the object later, it doesn't save unnecessary data.
	 */
	public function doPreStore() {
		parent::doPreStore();
		unset( $this->m_oOptions );
	}
	
	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->m_sUniqueToken = '';
		$this->m_sCommentStatus = '';
		$this->m_nCommentCooldown = self::DefaultCommentCooldown;
		$this->m_nCommentTokenExpire = self::DefaultCommentExpire;
	}
	
	/**
	 * 
	 * @param ICWP_OptionsHandler_CommentsFilter $inoOptions
	 */
	public function run( $inoOptions ) {
		$this->reset();
		$this->m_oOptions = $inoOptions;
		
		// Add GASP checking to the comment form.
		if ( $this->m_oOptions->getOpt( 'enable_comments_gasp_protection' ) == 'Y' ) {
			$this->m_nCommentCooldown = ( $this->m_oOptions->getOpt( 'comments_cooldown_interval' ) < 0 )? $this->m_nCommentCooldown : $this->m_oOptions->getOpt( 'comments_cooldown_interval' );
			$this->m_nCommentTokenExpire = ( $this->m_oOptions->getOpt( 'comments_token_expire_interval' ) < 0 )? $this->m_nCommentTokenExpire : $this->m_oOptions->getOpt( 'comments_token_expire_interval' );
			add_action(	'comment_form',					array( $this, 'printGaspFormHook_Action' ), 1 );
			add_action(	'comment_form',					array( $this, 'printGaspFormParts_Action' ), 2 );
			add_filter( 'preprocess_comment',			array( $this, 'doGaspCommentCheck_Filter' ), 1, 1);
			add_filter( 'pre_comment_approved',			array( $this, 'doGaspStatusSet_Filter' ), 1, 1);
		}
	}
	
	/**
	 * @return void
	 */
	public function printGaspFormHook_Action() {
		
		if ( $this->m_oOptions->getOpt( 'enable_comments_gasp_protection_for_logged_in' ) != 'Y' && is_user_logged_in() ) {
			return;
		}
		global $post;
		if ( !isset( $post ) ) {
			return;
		}
		if ( $post->comment_status != 'open' ) {
			return;
		}
		$this->deleteOldPostCommentTokens( $post->ID );
		$this->createUniquePostCommentToken( $post->ID, $this->m_sUniqueToken );

		require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		$this->m_sUniqueFormId = ICWP_DataProcessor::GenerateRandomString( rand(7, 23), true );
		
		echo $this->getGaspCommentsHookHtml();
	}

	/**
	 * @return void
	 */
	public function printGaspFormParts_Action() {
		if ( !empty($this->m_sUniqueToken) ) {
			echo $this->getGaspCommentsHtml();
		}
	}
	
	/**
	 * @return string
	 */
	public function getGaspCommentsHookHtml() {
		$sId = $this->m_sUniqueFormId;
		$sReturn = '<p id="'.$sId.'"></p>';
		$sReturn .= '<input type="hidden" id="_sugar_sweet_email" name="sugar_sweet_email" value="" />';
		$sReturn .= '<input type="hidden" id="_comment_token" name="comment_token" value="'.$this->m_sUniqueToken.'" />';
		return $sReturn;
	}
	
	public function getGaspCommentsHtml() {

		$sId = $this->m_sUniqueFormId;
		$sConfirm = $this->m_oOptions->getOpt( 'custom_message_checkbox' );
		$sAlert = $this->m_oOptions->getOpt( 'custom_message_alert' );
		$sCommentWait = $this->m_oOptions->getOpt( 'custom_message_comment_wait' );
		if ( strpos( $sCommentWait, '%s' ) !== false ) {
			$sCommentWait = sprintf( $sCommentWait, $this->m_nCommentCooldown );
			$sJsCommentWait = str_replace( '%s', '"+nRemaining+"', $this->m_oOptions->getOpt( 'custom_message_comment_wait' ) );
			$sJsCommentWait = '"'.$sJsCommentWait.'"';
		}
		else {
			$sJsCommentWait = '"'. $this->m_oOptions->getOpt( 'custom_message_comment_wait' ).'"';
		}
		$sCommentReload = $this->m_oOptions->getOpt( 'custom_message_comment_reload' );

		$sReturn = "
			<script type='text/javascript'>
				
				function cb_click$sId() {
					cb_name$sId.value=cb$sId.name;
				}
				function check$sId() {
					if( cb$sId.checked != true ) {
						alert( \"$sAlert\" ); return false;
					}
					return true;
				}
				function reenableButton$sId() {
					nTimerCounter{$sId}++;
					nRemaining = $this->m_nCommentCooldown - nTimerCounter$sId;
					subbutton$sId.value	= $sJsCommentWait;
					if ( nTimerCounter$sId >= $this->m_nCommentCooldown ) {
						subbutton$sId.value = origButtonValue$sId;
						subbutton$sId.disabled = false;
						clearInterval( sCountdownTimer$sId );
					}
				}
				function redisableButton$sId() {
					subbutton$sId.value		= \"$sCommentReload\";
					subbutton$sId.disabled	= true;
				}
				
				var $sId				= document.getElementById('$sId');

				var cb$sId				= document.createElement('input');
				cb$sId.type				= 'checkbox';
				cb$sId.id				= 'checkbox$sId';
				cb$sId.name				= 'checkbox$sId';
				cb$sId.style.width		= '25px';
				cb$sId.onclick			= cb_click$sId;
			
				var label$sId			= document.createElement( 'label' );
				label$sId.htmlFor		= 'checkbox$sId';
				label$sId.innerHTML		= \"$sConfirm\";

				var cb_name$sId			= document.createElement('input');
				cb_name$sId.type		= 'hidden';
				cb_name$sId.name		= 'cb_nombre';

				$sId.appendChild( cb$sId );
				$sId.appendChild( label$sId );
				$sId.appendChild( cb_name$sId );

				var frm$sId					= cb$sId.form;
				frm$sId.onsubmit			= check$sId;

				if ( $this->m_nCommentCooldown > 0 || $this->m_nCommentTokenExpire > 0  ) {

					var subbuttonList$sId = frm$sId.querySelectorAll( 'input[type=\"submit\"]' );
					
					if ( typeof( subbuttonList$sId ) != \"undefined\") {
						subbutton$sId = subbuttonList{$sId}[0];
						if ( typeof( subbutton$sId ) != \"undefined\") {
						
							if ( $this->m_nCommentCooldown > 0 ) {
								subbutton$sId.disabled		= true;
								
								origButtonValue$sId			= subbutton$sId.value;
								subbutton$sId.value			= \"$sCommentWait\";
								
								nTimerCounter$sId			= 0;
								sCountdownTimer$sId			= setInterval( reenableButton$sId, 1000 );
							}
							
							if ( $this->m_nCommentTokenExpire > 0 ) {
								sTimeoutTimer$sId			= setTimeout( redisableButton$sId, 1000 * $this->m_nCommentTokenExpire - 2 );
							}
						}
					}
				}
			</script>
		";
		return $sReturn;
	}
	
	/**
	 * @param array $inaCommentData
	 * @return unknown|string
	 */
	public function doGaspCommentCheck_Filter( $inaCommentData ) {
		if ( is_user_logged_in() ) {
			return $inaCommentData;
		}
		if( !isset( $_POST['cb_nombre'] ) ) {
			$this->m_sCommentStatus = 'spam';
		}
		// we have the cb name, is it set?
		else if ( !isset( $_POST[ $_POST['cb_nombre'] ] ) ) {
			$this->m_sCommentStatus = 'spam';
		}
		// honeypot check
		else if ( isset( $_POST['sugar_sweet'] ) && $_POST['sugar_sweet'] !== '' ) {
			$this->m_sCommentStatus = 'spam';
		}
		// check the unique comment token is present
		else if ( !isset( $_POST['comment_token'] ) || !$this->checkCommentToken( $_POST['comment_token'], $inaCommentData['comment_post_ID'] ) ) {
			$this->m_sCommentStatus = 'spam';
		}
		if ( false && $this->m_sCommentStatus = 'spam' ) { //add option to die later
			wp_die( "Ding Dong the which is dead." );
		}
		return $inaCommentData;
	}
	
	protected function checkCommentToken( $insCommentToken, $insPostId ) {
		
		$sToken = esc_sql( $insCommentToken ); //just incase someone try to get funky.
		
		// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
		$sQuery = "
			SELECT *
				FROM `%s`
			WHERE
				`unique_token`		= '%s'
				AND `post_id`		= '%s'
				AND `ip_long`		= '%s'
				AND `deleted_at`	= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$sToken,
			$insPostId,
			$this->m_nRequestIp
		);
		$mResult = $this->selectCustomFromTable( $sQuery );

		if ( empty( $mResult ) || !is_array($mResult) || count($mResult) != 1 ) {
			return false;
		}
		else {
			// Only 1 chance is given per token, so we delete it
			$this->deleteUniquePostCommentToken( $sToken, $insPostId );
			
			// Did suficient time pass, or has it expired?
			$nNow = time();
			$aRecord = $mResult[0];
			$nInterval = $nNow - $aRecord['created_at'];
			if ( $nInterval < $this->m_nCommentCooldown
					|| ( $this->m_nCommentTokenExpire > 0 && $nInterval > $this->m_nCommentTokenExpire )
				) {
				return false;
			}
			return true;
		}
	}
	
	public function doGaspStatusSet_Filter( $sApprovalStatus ) {
		if( !empty( $this->m_sCommentStatus ) ){
			$sApprovalStatus = $this->m_sCommentStatus;
		}
		return $sApprovalStatus;
	}
	
	public function createTable() {

		// Set up comments ID table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`post_id` int(11) NOT NULL DEFAULT '0',
			`unique_token` varchar(32) NOT NULL DEFAULT '',
			`ip_long` bigint(20) NOT NULL DEFAULT '0',
			`created_at` int(15) NOT NULL DEFAULT '0',
			`deleted_at` int(15) NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$sSqlTables = sprintf( $sSqlTables, $this->m_sTableName );
		$mResult = $this->doSql( $sSqlTables );
	}
	
	/**
	 * 
	 * @param string $insUniqueToken
	 * @param string $insPostId
	 */
	protected function deleteUniquePostCommentToken( $insUniqueToken, $insPostId, $infSoftDelete = false ) {
		
		if ( $infSoftDelete ) {
			$nNow = time();
			$sQuery = "
					UPDATE `%s`
						SET `deleted_at`	= '%s'
					WHERE
						`unique_token`		= '%s'
						AND `post_id`		= '%s'
				";
			$sQuery = sprintf( $sQuery,
					$this->m_sTableName,
					$nNow,
					$sToken,
					$insPostId
			);
			$this->doSql( $sQuery );
		}
		else {
			$aWhere['unique_token']	= $insUniqueToken;
			$aWhere['post_id']		= $insPostId;
			$this->deleteRowsFromTable( $aWhere );
		}
	}
	
	/**
	 * 
	 * @param string $insUniqueToken
	 * @param string $insPostId
	 */
	protected function deleteOldPostCommentTokens( $insPostId, $infSoftDelete = false ) {
		
		if ( $infSoftDelete ) {
			$nNow = time();
			$sQuery = "
					UPDATE `%s`
						SET `deleted_at`	= '%s'
					WHERE
						`ip_long`			= '%s'
						AND `post_id`		= '%s'
				";
			$sQuery = sprintf( $sQuery,
					$this->m_sTableName,
					$nNow,
					$this->m_nRequestIp,
					$insPostId
			);
			$this->doSql( $sQuery );
		}
		else {
			$aWhere['ip_long']		= $this->m_nRequestIp;
			$aWhere['post_id']		= $insPostId;
			$this->deleteRowsFromTable( $aWhere );
		}
	}
	
	protected function createUniquePostCommentToken( $insPostId, &$outsUniqueToken = '' ) {

		// Now add new pending entry
		$nNow = time();
		$outsUniqueToken = $this->getUniqueToken( $insPostId );
		$aData = array();
		$aData[ 'post_id' ]			= $insPostId;
		$aData[ 'unique_token' ]	= $outsUniqueToken;
		$aData[ 'ip_long' ]			= $this->m_nRequestIp;
		$aData[ 'created_at' ]		= $nNow;
		
		$mResult = $this->insertIntoTable( $aData );
		return $mResult;
	}
	
	protected function getUniqueToken( $insPostId ) {
		$sToken = uniqid( $this->m_nRequestIp.$insPostId );
		return md5( $sToken );
	}
	
	/**
	 * Should return false when logging is disabled.
	 *
	 * @return false|array	- false when logging is disabled, array with log data otherwise
	 * @see ICWP_BaseProcessor_WPSF::getLogData()
	 */
	public function flushLogData() {
	
		if ( !$this->m_fLoggingEnabled || empty( $this->m_aLogMessages ) ) {
			return false;
		}

		$this->m_aLog = array(
			'category'			=> self::LOG_CATEGORY_LOGINPROTECT,
			'messages'			=> serialize( $this->m_aLogMessages )
		);
		$this->resetLog();
		return $this->m_aLog;
	}
}

endif;