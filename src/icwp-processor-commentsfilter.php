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

if ( !class_exists('ICWP_CommentsFilterProcessor_V2') ):

class ICWP_CommentsFilterProcessor_V2 extends ICWP_BaseDbProcessor_WPSF {

	const Slug = 'comments_filter';
	const TableName = 'comments_filter';
	const Spam_Blacklist_Source = 'https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt';

	const TWODAYS = 172800;

	/**
	 * @var string
	 */
	static protected $sSpamBlacklistFile;

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
	 * The maxium length of time that comment token may last and be used.
	 * @var integer
	 */
	protected $m_nCommentTokenExpire;
	/**
	 * @var integer
	 */
	protected $m_nLastLoginTime;
	/**
	 * @var string
	 */
	protected $m_sSecretKey;
	/**
	 * @var string
	 */
	protected $m_sGaspKey;
	/**
	 * @var string
	 */
	protected $sCommentStatus;
	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation;

	/**
	 * Flag as to whether Two Factor Authentication will be by-passed when sending the verification
	 * email fails.
	 * 
	 * @var boolean
	 */
	protected $m_fAllowTwoFactorByPass;

	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::Slug, self::TableName );
		$this->createTable();
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->m_sUniqueToken = '';
		$this->sCommentStatus = '';
		$this->sCommentStatusExplanation = '';
		self::$sSpamBlacklistFile = dirname(__FILE__).ICWP_DS.'..'.ICWP_DS.'resources'.ICWP_DS.'spamblacklist.txt';
	}
	
	/**
	 */
	public function run() {
		parent::run();

		$fDoSetCommentStatus = false;

		// Add GASP checking to the comment form.
		if ( $this->getIsOption('enable_comments_gasp_protection', 'Y') ) {
			add_action(	'comment_form',					array( $this, 'printGaspFormHook_Action' ), 1 );
			add_action(	'comment_form',					array( $this, 'printGaspFormParts_Action' ), 2 );
		}

		add_filter( 'preprocess_comment',			array( $this, 'doCommentPreProcess_Filter' ), 1, 1 );
		add_filter( 'pre_comment_content',			array( $this, 'doCommentContentPreProcess_Filter' ), 1, 1 );
		add_filter( 'pre_comment_approved',			array( $this, 'doSetCommentStatus_Filter' ), 1 );
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentPreProcess_Filter( $aCommentData ) {

		if ( !$this->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$this->doGaspCommentCheck( $aCommentData['comment_post_ID'] );
		$this->doBlacklistSpamCheck( $aCommentData );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( $this->sCommentStatus == 'reject' ) {
			$oWpFunctions = $this->loadWpFunctionsProcessor();
			$oWpFunctions->redirectToHome();
		}

		return $aCommentData;
	}

	/**
	 * @param string $sCommentContent
	 * @return string
	 */
	public function doCommentContentPreProcess_Filter( $sCommentContent ) {
		// If either spam filtering process left an explanation, we add it here
		if ( !empty( $this->sCommentStatusExplanation ) ) {
			$sCommentContent = $this->sCommentStatusExplanation.$sCommentContent;
		}
		return $sCommentContent;
	}

	/**
	 * Performs the actual GASP comment checking
	 *
	 * @param $nPostId
	 */
	protected function doGaspCommentCheck( $nPostId ) {

		//Check that we haven't already marked the comment through another scan
		if ( !empty( $this->sCommentStatus ) || !$this->getIsOption('enable_comments_gasp_protection', 'Y') ) {
			return;
		}

		$fIsSpam = true;
		$sExplanation = '';

		// we have the cb name, is it set?
		if( !isset( $_POST['cb_nombre'] ) || !isset( $_POST[ $_POST['cb_nombre'] ] ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('checkbox') );
			$sStatKey = 'checkbox';
		}
		// honeypot check
		else if ( isset( $_POST['sugar_sweet_email'] ) && $_POST['sugar_sweet_email'] !== '' ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('honeypot') );
			$sStatKey = 'honeypot';
		}
		// check the unique comment token is present
		else if ( !isset( $_POST['comment_token'] ) || !$this->checkCommentToken( $_POST['comment_token'], $nPostId ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('comment token failure') );
			$sStatKey = 'token';
		}
		else {
			$fIsSpam = false;
		}

		if ( $fIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.gasp.%s', $sStatKey ) );
			$this->sCommentStatus = $this->getOption('comments_default_action_spam_bot');
			$this->setCommentStatusExplanation( $sExplanation );
		}
	}

	/**
	 * @param $aCommentData
	 */
	protected function doBlacklistSpamCheck( $aCommentData ) {
		$this->loadDataProcessor();
		$this->doBlacklistSpamCheck_Action(
			$aCommentData['comment_author'],
			$aCommentData['comment_author_email'],
			$aCommentData['comment_author_url'],
			$aCommentData['comment_content'],
			ICWP_WPSF_DataProcessor::GetVisitorIpAddress( false ),
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : ''
		);
	}

	/**
	 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher performance.
	 *
	 * It also uses defined options for which fields are checked for SPAM instead of just checking EVERYTHING!
	 *
	 * @param string $sAuthor
	 * @param string $sEmail
	 * @param string $sUrl
	 * @param string $sComment
	 * @param string $sUserIp
	 * @param string $sUserAgent
	 */
	public function doBlacklistSpamCheck_Action( $sAuthor, $sEmail, $sUrl, $sComment, $sUserIp, $sUserAgent ) {

		// Check that we haven't already marked the comment through another scan, say GASP
		if ( !empty( $this->sCommentStatus ) || !$this->getIsOption('enable_comments_human_spam_filter', 'Y') ) {
			return;
		}

		// read the file of spam words
		$sSpamWords = $this->getSpamBlacklist();
		if ( empty($sSpamWords) ) {
			return;
		}
		$aWords = explode( "\n", $sSpamWords );

		$aItemsMap = array(
			'comment_content'	=> $sComment,
			'url'				=> $sUrl,
			'author_name'		=> $sAuthor,
			'author_email'		=> $sEmail,
			'ip_address'		=> $sUserIp,
			'user_agent'		=> $sUserAgent
		);
		$aDesiredItemsToCheck = $this->getOption('enable_comments_human_spam_filter_items');
		$aItemsToCheck = array();
		foreach( $aDesiredItemsToCheck as $sKey ) {
			$aItemsToCheck[$sKey] = $aItemsMap[$sKey];
		}

		foreach( $aItemsToCheck as $sKey => $sItem ) {
			foreach ( $aWords as $sWord ) {
				if ( stripos( $sItem, $sWord ) !== false ) {
					//mark as spam and exit;
					$this->doStatIncrement( sprintf( 'spam.human.%s', $sKey ) );
					$this->doStatHumanSpamWords( $sWord );
					$this->sCommentStatus = $this->getOption('comments_default_action_human_spam');
					$this->setCommentStatusExplanation( sprintf( _wpsf__('Human SPAM filter found "%s" in "%s"' ), $sWord, $sKey ) );
					break 2;
				}
			}
		}
	}

	/**
	 * @param $sStatWord
	 */
	protected function doStatHumanSpamWords( $sStatWord = '' ) {
		$this->loadWpsfStatsProcessor();
		if ( !empty($sStatWord) ) {
			ICWP_Stats_WPSF::DoStatIncrementKeyValue( 'spam.human.words', base64_encode( $sStatWord ) );
		}
	}

	/**
	 * @return null|string
	 */
	protected function getSpamBlacklist() {
		$oFs = $this->loadFileSystemProcessor();

		// first, does the file exist? If not import
		if ( !$oFs->exists( self::$sSpamBlacklistFile ) ) {
			$this->doSpamBlacklistImport();
		}
		// second, if it exists and it's older than 48hrs, update
		else if ( time() - $oFs->getModifiedTime( self::$sSpamBlacklistFile ) > self::TWODAYS ) {
			$this->doSpamBlacklistUpdate();
		}

		$sList = $oFs->getFileContent( self::$sSpamBlacklistFile );
		return empty($sList)? '' : $sList;
	}

	/**
	 */
	protected function doSpamBlacklistUpdate() {
		$oFs = $this->loadFileSystemProcessor();
		$oFs->deleteFile( self::$sSpamBlacklistFile );
		$this->doSpamBlacklistImport();
	}

	/**
	 */
	protected function doSpamBlacklistImport() {
		$oFs = $this->loadFileSystemProcessor();
		if ( !$oFs->exists( self::$sSpamBlacklistFile ) ) {

			$sRawList = $this->doSpamBlacklistDownload();

			if ( empty($sRawList) ) {
				$sList = '';
			}
			else {
				// filter out empty lines
				$aWords = explode( "\n", $sRawList );
				foreach ( $aWords as $nIndex => $sWord ) {
					$sWord = trim($sWord);
					if ( empty($sWord) ) {
						unset( $aWords[$nIndex] );
					}
				}
				$sList = implode( "\n", $aWords );
			}

			// save the list to disk for the future.
			$oFs->putFileContent( self::$sSpamBlacklistFile, $sList );
		}
	}

	/**
	 * @return string
	 */
	protected function doSpamBlacklistDownload() {
		$oFs = $this->loadFileSystemProcessor();
		return $oFs->getUrlContent( self::Spam_Blacklist_Source );
	}
	
	/**
	 * @return void
	 */
	public function printGaspFormHook_Action() {
		
		if ( !$this->getIfDoCommentsCheck() ) {
			return;
		}

		global $post;
		if ( !isset( $post ) || $post->comment_status != 'open' ) {
			return;
		}
		$this->deleteOldPostCommentTokens( $post->ID );
		$this->createUniquePostCommentToken( $post->ID, $this->m_sUniqueToken );

		require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		$this->m_sUniqueFormId = ICWP_WPSF_DataProcessor::GenerateRandomString( rand(7, 23), true );
		
		echo $this->getGaspCommentsHookHtml();
	}
	
	/**
	 * Tells us whether, for this particular comment post, if we should do comments checking.
	 * 
	 * @return boolean
	 */
	protected function getIfDoCommentsCheck() {
		if ( !is_user_logged_in() ) {
			return true;
		}
		else if ( $this->getIsOption('enable_comments_gasp_protection_for_logged_in', 'Y') ) {
			return true;
		}
		return false;
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
		$sReturn .= '<input type="hidden" id="_comment_token" name="comment_token" value="'.$this->m_sUniqueToken.'" />';
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
			$this->m_sTableName,
			$sToken,
			$sPostId,
			$this->m_nRequestIp
		);
		$mResult = $this->selectCustomFromTable( $sQuery );

		if ( empty( $mResult ) || !is_array($mResult) || count($mResult) != 1 ) {
			return false;
		}
		else {
			// Only 1 chance is given per token, so we delete it
			$this->deleteUniquePostCommentToken( $sToken, $sPostId );
			
			// Did sufficient time pass, or has it expired?
			$nNow = time();
			$aRecord = $mResult[0];
			$nInterval = $nNow - $aRecord['created_at'];
			if ( $nInterval < $this->m_aOptions[ 'comments_cooldown_interval' ]
					|| ( $this->getOption( 'comments_token_expire_interval' ) > 0 && $nInterval > $this->getOption('comments_token_expire_interval') )
				) {
				return false;
			}
			return true;
		}
	}

	/**
	 * We set the final approval status of the comments if we've set it in our scans, and empties the notification email
	 * in case we "trash" it (since WP sends out a notification email if it's anything but SPAM)
	 *
	 * @param $sApprovalStatus
	 * @return string
	 */
	public function doSetCommentStatus_Filter( $sApprovalStatus ) {
		add_filter( 'comment_notification_recipients', array( $this, 'doClearCommentNotificationEmail_Filter' ), 100, 1 );
		return empty( $this->sCommentStatus )? $sApprovalStatus : $this->sCommentStatus;
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 *
	 * @param $aEmails
	 * @return array
	 */
	public function doClearCommentNotificationEmail_Filter( $aEmails ) {
		if ( $this->sCommentStatus == 'trash' ) {
			$aEmails = array();
		}
		return $aEmails;
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
		return $this->doSql( $sSqlTables );
	}

	/**
	 * @param string $insUniqueToken
	 * @param string $insPostId
	 * @param boolean $infSoftDelete
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
			$aWhere = array();
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
		$nTimeStamp = time() - DAY_IN_SECONDS;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}
endif;

if ( !class_exists('ICWP_WPSF_CommentsFilterProcessor') ):
	class ICWP_WPSF_CommentsFilterProcessor extends ICWP_CommentsFilterProcessor_V2 { }
endif;