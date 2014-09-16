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

if ( !class_exists('ICWP_WPSF_Processor_CommentsFilter_HumanSpam') ):

class ICWP_WPSF_Processor_CommentsFilter_HumanSpam extends ICWP_WPSF_Processor_Base {

	const Spam_Blacklist_Source = 'https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt';

	const TWODAYS = 172800;

	/**
	 * @var string
	 */
	static protected $sSpamBlacklistFile;

	/**
	 * @var string
	 */
	protected $sCommentStatus = '';
	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation = '';

	/**
	 * @param ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->sCommentStatus = '';
		$this->sCommentStatusExplanation = '';
		self::$sSpamBlacklistFile = $this->oFeatureOptions->getResourcesDir().'spamblacklist.txt';
	}
	
	/**
	 */
	public function run() {
		add_filter( 'preprocess_comment',			array( $this, 'doCommentChecking' ), 1, 1 );

		add_filter( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status' ), array( $this, 'getCommentStatus' ), 2 );
		add_filter( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status_explanation' ), array( $this, 'getCommentStatusExplanation' ), 2 );
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
	 * Tells us whether, for this particular comment post, if we should do comments checking.
	 *
	 * @return boolean
	 */
	protected function getIfDoCommentsCheck() {

		// First, are comments allowed on this post?
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
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {

		if ( !$this->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$this->doBlacklistSpamCheck( $aCommentData );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( $this->sCommentStatus == 'reject' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->redirectToHome();
		}

		return $aCommentData;
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
			long2ip( self::$nRequestIp ),
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
					$this->sCommentStatus = $this->getOption( 'comments_default_action_human_spam' );
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
		$this->loadStatsProcessor();
		if ( !empty( $sStatWord ) ) {
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
		else if ( self::$nRequestTimestamp - $oFs->getModifiedTime( self::$sSpamBlacklistFile ) > self::TWODAYS ) {
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
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		$this->sCommentStatusExplanation =
			'[* '.sprintf( _wpsf__('WordPress Simple Firewall plugin marked this comment as "%s" because: %s.'),
				$this->sCommentStatus,
				$sExplanation
			)." *]\n";
	}
}
endif;