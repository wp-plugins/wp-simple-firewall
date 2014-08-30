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

if ( !class_exists('ICWP_WPSF_Processor_CommentsFilter_V2') ):

class ICWP_WPSF_Processor_CommentsFilter_V2 extends ICWP_WPSF_Processor_Base {

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
	}
	
	/**
	 */
	public function run() {
		parent::run();

		if ( $this->getIsOption( 'enable_comments_gasp_protection', 'Y' ) ) {
			require_once('icwp-processor-commentsfilter_antibotspam.php');
			$oBotSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam( $this->oFeatureOptions );
			$oBotSpamProcessor->run();
		}

		$oWp = $this->loadWpFunctionsProcessor();
		$oDp = $this->loadDataProcessor();
		if ( $oDp->GetIsRequestPost() && $oWp->getCurrentPage() == 'wp-comments-post.php' && $this->getIsOption( 'enable_comments_human_spam_filter', 'Y' ) ) {
			require_once('icwp-processor-commentsfilter_humanspam.php');
			$oHumanSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_HumanSpam( $this->oFeatureOptions );
			$oHumanSpamProcessor->run();
		}

		add_filter( 'pre_comment_approved',				array( $this, 'doSetCommentStatus' ), 1 );
		add_filter( 'pre_comment_content',				array( $this, 'doInsertCommentStatusExplanation' ), 1, 1 );
		add_filter( 'comment_notification_recipients',	array( $this, 'doClearCommentNotificationEmail_Filter' ), 100, 1 );
	}

	/**
	 * We set the final approval status of the comments if we've set it in our scans, and empties the notification email
	 * in case we "trash" it (since WP sends out a notification email if it's anything but SPAM)
	 *
	 * @param $sApprovalStatus
	 * @return string
	 */
	public function doSetCommentStatus( $sApprovalStatus ) {
		$sStatus = apply_filters( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status' ), '' );
		return empty( $sStatus ) ? $sApprovalStatus : $sStatus;
	}

	/**
	 * @param string $sCommentContent
	 * @return string
	 */
	public function doInsertCommentStatusExplanation( $sCommentContent ) {

		$sExplanation = apply_filters( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status_explanation' ), '' );

		// If either spam filtering process left an explanation, we add it here
		if ( !empty( $sExplanation ) ) {
			$sCommentContent = $sExplanation.$sCommentContent;
		}
		return $sCommentContent;
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 *
	 * @param array $aEmails
	 * @return array
	 */
	public function doClearCommentNotificationEmail_Filter( $aEmails ) {
		$sStatus = apply_filters( $this->oFeatureOptions->doPluginPrefix( 'comments_filter_status' ), '' );
		if ( $sStatus == 'trash' ) {
			$aEmails = array();
		}
		return $aEmails;
	}

}
endif;

if ( !class_exists('ICWP_WPSF_Processor_CommentsFilter') ):
	class ICWP_WPSF_Processor_CommentsFilter extends ICWP_WPSF_Processor_CommentsFilter_V2 { }
endif;
