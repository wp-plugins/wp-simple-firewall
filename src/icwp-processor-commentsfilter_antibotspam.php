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

require_once( dirname(__FILE__).'/icwp-basedb-processor.php' );

if ( !class_exists('ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam') ):

class ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam extends ICWP_WPSF_BaseDbProcessor {

	const TableName = 'comments_filter';

	/**
	 * The unique comment token assigned to this page
	 * @var integer
	 */
	protected $sUniqueCommentToken;
	/**
	 * The unique comment token assigned to this page
	 * @var integer
	 */
	protected $m_sUniqueFormId;
	/**
	 * @var string
	 */
	protected $sCommentStatus;
	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation;

	/**
	 * @param ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, self::TableName );
		$this->createTable();
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->sUniqueCommentToken = '';
		$this->sCommentStatus = '';
		$this->sCommentStatusExplanation = '';
	}
	
	/**
	 */
	public function run() {
		parent::run();

		// Add GASP checking to the comment form.
		add_action(	'comment_form',					array( $this, 'printGaspFormHook_Action' ), 1 );
		add_action(	'comment_form',					array( $this, 'printGaspFormParts_Action' ), 2 );
		add_filter( 'preprocess_comment',			array( $this, 'doCommentChecking' ), 1, 1 );

		add_filter( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status' ), array( $this, 'getCommentStatus' ), 1 );
		add_filter( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status_explanation' ), array( $this, 'getCommentStatusExplanation' ), 1 );
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status.
	 *
	 * @param $sCurrentCommentStatus
	 * @return string
	 */
	public function getCommentStatus( $sCurrentCommentStatus ) {
		return empty( $sCurrentCommentStatus )? $this->sCommentStatus : $sCurrentCommentStatus;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status explanation
	 *
	 * @param $sCurrentCommentStatusExplanation
	 * @return string
	 */
	public function getCommentStatusExplanation( $sCurrentCommentStatusExplanation ) {
		return empty( $sCurrentCommentStatusExplanation )? $this->sCommentStatusExplanation : $sCurrentCommentStatusExplanation;
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {

		if ( !$this->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$this->doGaspCommentCheck( $aCommentData['comment_post_ID'] );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( $this->sCommentStatus == 'reject' ) {
			$oWpFunctions = $this->loadWpFunctionsProcessor();
			$oWpFunctions->redirectToHome();
		}

		return $aCommentData;
	}

	/**
	 * Performs the actual GASP comment checking
	 *
	 * @param $nPostId
	 */
	protected function doGaspCommentCheck( $nPostId ) {

		if ( !$this->getIfDoGaspCheck() ) {
			return;
		}

		// Check that we haven't already marked the comment through another scan
		if ( !empty( $this->sCommentStatus ) || !$this->getIsOption( 'enable_comments_gasp_protection', 'Y' ) ) {
			return;
		}

		$fIsSpam = true;
		$sExplanation = '';

		$this->loadDataProcessor();

		$sFieldCheckboxName = ICWP_WPSF_DataProcessor::FetchPost( 'cb_nombre' );
		$sFieldHoney = ICWP_WPSF_DataProcessor::FetchPost( 'sugar_sweet_email' );
		$sFieldCommentToken = ICWP_WPSF_DataProcessor::FetchPost( 'comment_token' );

		// we have the cb name, is it set?
		if( !$sFieldCheckboxName || !ICWP_WPSF_DataProcessor::FetchPost( $sFieldCheckboxName ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('checkbox') );
			$sStatKey = 'checkbox';
		}
		// honeypot check
		else if ( !empty( $sFieldHoney ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('honeypot') );
			$sStatKey = 'honeypot';
		}
		// check the unique comment token is present
		else if ( empty( $sFieldCommentToken ) || !$this->checkCommentToken( $sFieldCommentToken, $nPostId ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('comment token failure') );
			$sStatKey = 'token';
		}
		else {
			$fIsSpam = false;
		}

		if ( $fIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.gasp.%s', $sStatKey ) );
			$this->sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );
		}
	}

	/**
	 * @return void
	 */
	public function printGaspFormHook_Action() {
		
		if ( !$this->getIfDoCommentsCheck() ) {
			return;
		}

		$this->deleteOldPostCommentTokens();
		$this->insertUniquePostCommentToken();

		$this->loadDataProcessor();
		$this->m_sUniqueFormId = ICWP_WPSF_DataProcessor::GenerateRandomString( rand(7, 23), true );
		
		echo $this->getGaspCommentsHookHtml();
	}
	
	/**
	 * Tells us whether, for this particular comment post, if we should do comments checking.
	 * 
	 * @return boolean
	 */
	protected function getIfDoCommentsCheck() {

		// Compatibility with shoutbox WP Wall Plugin
		// http://wordpress.org/plugins/wp-wall/
		if ( !$this->getIfDoGaspCheck() ) {
			return false;
		}

		//First, are comments allowed on this post?
		global $post;
		if ( !isset( $post ) || $post->comment_status != 'open' ) {
			return false;
		}

		if ( !is_user_logged_in() ) {
			return true;
		}
		else if ( $this->getIsOption('enable_comments_gasp_protection_for_logged_in', 'Y') ) {
			return true;
		}
		return false;
	}

	/**
	 * Tells us whether, for this particular comment post, if we should do GASP comments checking.
	 *
	 * @return boolean
	 */
	protected function getIfDoGaspCheck() {

		// Compatibility with shoutbox WP Wall Plugin
		// http://wordpress.org/plugins/wp-wall/
		if ( function_exists( 'WPWall_Init' ) ) {
			$this->loadDataProcessor();
			if ( !is_null( ICWP_WPSF_DataProcessor::FetchPost('submit_wall_post') ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return void
	 */
	public function printGaspFormParts_Action() {
		if ( $this->getIfDoCommentsCheck() ) {
			echo $this->getGaspCommentsHtml();
		}
	}
	
	/**
	 * @return string
	 */
	protected function getGaspCommentsHookHtml() {
		$sId = $this->m_sUniqueFormId;
		$sReturn = '<p id="'.$sId.'"></p>'; // we use this unique <p> to hook onto using javascript
		$sReturn .= '<input type="hidden" id="_sugar_sweet_email" name="sugar_sweet_email" value="" />';
		$sReturn .= '<input type="hidden" id="_comment_token" name="comment_token" value="'.$this->getUniqueCommentToken().'" />';
		return $sReturn;
	}
	
	protected function getGaspCommentsHtml() {

		$sId			= $this->m_sUniqueFormId;
		$sConfirm		= stripslashes( $this->getOption('custom_message_checkbox') );
		$sAlert			= stripslashes( $this->getOption('custom_message_alert') );
		$sCommentWait	= stripslashes( $this->getOption('custom_message_comment_wait') );
		$nCooldown		= $this->getOption('comments_cooldown_interval');
		$nExpire		= $this->getOption('comments_token_expire_interval');

		if ( strpos( $sCommentWait, '%s' ) !== false ) {
			$sCommentWait = sprintf( $sCommentWait, $nCooldown );
			$sJsCommentWait = str_replace( '%s', '"+nRemaining+"', $this->getOption('custom_message_comment_wait') );
			$sJsCommentWait = '"'.$sJsCommentWait.'"';
		}
		else {
			$sJsCommentWait = '"'. $this->getOption('custom_message_comment_wait').'"';
		}
		$sCommentReload = $this->getOption('custom_message_comment_reload');

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
					nRemaining = $nCooldown - nTimerCounter$sId;
					subbutton$sId.value	= $sJsCommentWait;
					if ( nTimerCounter$sId >= $nCooldown ) {
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

				".(
					( $nCooldown > 0 || $nExpire > 0 ) ?
					"
					var subbuttonList$sId = frm$sId.querySelectorAll( 'input[type=\"submit\"]' );
					
					if ( typeof( subbuttonList$sId ) != \"undefined\" ) {
						subbutton$sId = subbuttonList{$sId}[0];
						if ( typeof( subbutton$sId ) != \"undefined\" ) {
						
						".(
							( $nCooldown > 0 )?
							"
							subbutton$sId.disabled		= true;
							origButtonValue$sId			= subbutton$sId.value;
							subbutton$sId.value			= \"$sCommentWait\";
							nTimerCounter$sId			= 0;
							sCountdownTimer$sId			= setInterval( reenableButton$sId, 1000 );
							"
							:''
						).(
							( $nExpire > 0 )? "sTimeoutTimer$sId			= setTimeout( redisableButton$sId, ".(1000 * $nExpire - 1000)." );" : ''
						)."
						}
					}
					":''
				)."
			</script>
		";
		return $sReturn;
	}

	/**
	 * @param $sCommentToken
	 * @param $sPostId
	 * @return bool
	 */
	protected function checkCommentToken( $sCommentToken, $sPostId ) {

		$sToken = esc_sql( $sCommentToken ); //just in-case someones tries to get all funky up in it
		
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
			$this->getTableName(),
			$sToken,
			$sPostId,
			self::$nRequestIp
		);
		$mResult = $this->selectCustomFromTable( $sQuery );

		if ( empty( $mResult ) || !is_array($mResult) || count($mResult) != 1 ) {
			return false;
		}
		else {
			// Only 1 chance is given per token, so we delete it
			$this->deleteUniquePostCommentToken( $sToken, $sPostId );
			
			// Did sufficient time pass, or has it expired?
			$aRecord = $mResult[0];
			$nInterval = self::$nRequestTimestamp - $aRecord['created_at'];
			if ( $nInterval < $this->getOption( 'comments_cooldown_interval' )
					|| ( $this->getOption( 'comments_token_expire_interval' ) > 0 && $nInterval > $this->getOption('comments_token_expire_interval') )
				) {
				return false;
			}
			return true;
		}
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
		$sSqlTables = sprintf( $sSqlTables, $this->getTableName() );
		return $this->doSql( $sSqlTables );
	}

	/**
	 * @param string $insUniqueToken
	 * @param string $insPostId
	 * @param boolean $infSoftDelete
	 */
	protected function deleteUniquePostCommentToken( $insUniqueToken, $insPostId, $infSoftDelete = false ) {

		if ( $infSoftDelete ) {
			$sQuery = "
					UPDATE `%s`
						SET `deleted_at`	= '%s'
					WHERE
						`unique_token`		= '%s'
						AND `post_id`		= '%s'
				";
			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				self::$nRequestTimestamp,
				$insUniqueToken,
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
	 * @param bool $fSoftDelete
	 * @param string $sPostId
	 */
	protected function deleteOldPostCommentTokens( $fSoftDelete = false, $sPostId = null ) {

		$nPostIdToDelete = empty( $sPostId ) ? $this->getRequestPostId() : $sPostId;

		if ( $fSoftDelete ) {
			$sQuery = "
					UPDATE `%s`
						SET `deleted_at`	= '%s'
					WHERE
						`ip_long`			= '%s'
						AND `post_id`		= '%s'
				";
			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				self::$nRequestTimestamp,
				self::$nRequestIp,
				$nPostIdToDelete
			);
			$this->doSql( $sQuery );
		}
		else {
			$aWhere = array();
			$aWhere['ip_long']		= self::$nRequestIp;
			$aWhere['post_id']		= $nPostIdToDelete;
			$this->deleteRowsFromTable( $aWhere );
		}
	}

	/**
	 * @return mixed
	 */
	protected function insertUniquePostCommentToken() {

		$aData = array();
		$aData[ 'post_id' ]			= $this->getRequestPostId();
		$aData[ 'unique_token' ]	= $this->getUniqueCommentToken();
		$aData[ 'ip_long' ]			= self::$nRequestIp;
		$aData[ 'created_at' ]		= self::$nRequestTimestamp;
		
		$mResult = $this->insertIntoTable( $aData );
		return $mResult;
	}

	/**
	 * @return string
	 */
	protected function generateUniqueToken() {
		$sToken = uniqid( self::$nRequestIp.self::$nRequestTimestamp.$this->getRequestPostId() );
		return md5( $sToken );
	}

	/**
	 * @return string
	 */
	protected function getUniqueCommentToken() {
		if ( empty( $this->sUniqueCommentToken ) ) {
			$this->sUniqueCommentToken = $this->generateUniqueToken();
		}
		return $this->sUniqueCommentToken;
	}

	/**
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		$this->sCommentStatusExplanation =
			'[* '.sprintf( _wpsf__('WordPress Simple Firewall plugin marked this comment as "%s" because: %s.'),
				$this->sCommentStatus,
				$sExplanation
			)." *]\n";
	}
	
	/**
	 * This is hooked into a cron in the base class and overrides the parent method.
	 * 
	 * It'll delete everything older than 24hrs.
	 */
	public function cleanupDatabase() {
		if ( !$this->getTableExists() ) {
			return;
		}
		$nTimeStamp = self::$nRequestTimestamp - DAY_IN_SECONDS;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}
endif;
