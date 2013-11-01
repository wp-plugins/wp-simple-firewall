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

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_OptionsHandler_CommentsFilter') ):

class ICWP_OptionsHandler_CommentsFilter extends ICWP_OptionsHandler_Base_WPSF {

	const StoreName = 'commentsfilter_options';
	
	const DefaultCommentCooldown	= 30; //seconds.
	const DefaultCommentExpire		= 600; //seconds.
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array();
		
		$aBase = array(
			'section_title' => 'Enable Comments Filter',
			'section_options' => array(
				array(
					'enable_comments_filter',
					'',
					'Y',
					'checkbox',
					'Enable Comments Filter',
					'Enable (or Disable) The Comments Filter Feature',
					'Regardless of any other settings, this option will turn Off the Comments Filter feature, or enable your chosen Comments Filter options.'
				)
			),
		);
		$aGasp = array(
			'section_title' => 'G.A.S.P. Comment SPAM Protection',
			'section_options' => array(
				array(
					'enable_comments_gasp_protection',
					'',
					'Y',
					'checkbox',
					'GASP Protection',
					'Add Growmap Anti Spambot Protection to your comments',
					'Taking the lead from the original GASP plugin for WordPress, we have extended it to include further protection. '.sprintf( '[%smore info%s]', '<a href="http://icwp.io/2n" target="_blank">', '</a>' )
				),
				array(
					'enable_comments_gasp_protection_for_logged_in',
					'',
					'N',
					'checkbox',
					'Include Logged-In Users',
					'You may also enable GASP for logged in users',
					'Since logged-in users would be expected to be vetted, this is off by default.'
				),
				array(
					'comments_cooldown_interval',
					'',
					'30',
					'integer',
					'Comments Cooldown',
					'Limit posting a comment to X seconds after the page has loaded',
					"By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts."
				),
				array(
					'comments_token_expire_interval',
					'',
					'600',
					'integer',
					'Comment Token Expire',
					'A visitor has X seconds within which to post a comment',
					"Default: 10 minutes (600 seconds). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors."
				),
				array(
					'custom_message_checkbox',
					'',
					"I'm not a spammer.",
					'text',
					'Custom Checkbox Message',
					"If you want a custom checkbox message, please specify this here.",
					"You can customise the message beside the checkbox. Default: I'm not a spammer"
				),
				array(
					'custom_message_alert',
					'',
					"Please check the box to confirm you're not a spammer",
					'text',
					'Custom Alert Message',
					"If you want a custom alert message, please specify this here.",
					"Default: Please check the box to confirm you're not a spammer"
				),
				array(
					'custom_message_comment_wait',
					'',
					"Please wait %s seconds before posting your comment",
					'text',
					'Custom Alert Message',
					"If you want a custom submit-button message please specify this here.",
					"Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these.
					<br />Default: Please wait %s seconds before posting your comment"
				),
				array(
					'custom_message_comment_reload',
					'',
					"Please reload this page to post a comment",
					'text',
					'Custom Alert Message',
					"This message is displayed on the submit-button when the comment token is expired.",
					"Default: Please reload this page to post a comment"
				)
			)
		);

		$this->m_aOptions = array(
			$aBase,
			$aGasp
		);
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		$nCommentCooldown = $this->getOpt( 'comments_cooldown_interval' );
		if ( $nCommentCooldown < 0 ) {
			$nCommentCooldown = 0;
		}
		
		$nCommentTokenExpire = $this->getOpt( 'comments_token_expire_interval' );
		if ( $nCommentTokenExpire < 0 ) {
			$nCommentTokenExpire = 0;
		}
		
		if ( $nCommentTokenExpire != 0 && $nCommentCooldown > $nCommentTokenExpire ) {
			$nCommentCooldown = self::DefaultCommentCooldown;
			$nCommentTokenExpire = self::DefaultCommentExpire;
		}
		$this->setOpt( 'comments_cooldown_interval', $nCommentCooldown );
		$this->setOpt( 'comments_token_expire_interval', $nCommentTokenExpire );
	}
	
	public function updateHandler() {
		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
	}
}

endif;