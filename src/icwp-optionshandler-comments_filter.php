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

if ( !class_exists('ICWP_WPSF_FeatureHandler_CommentsFilter') ):

class ICWP_WPSF_FeatureHandler_CommentsFilter extends ICWP_WPSF_FeatureHandler_Base {

	const DefaultCommentCooldown	= 30; //seconds.
	const DefaultCommentExpire		= 600; //seconds.
	
	/**
	 * @var ICWP_WPSF_Processor_CommentsFilter
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Comments Filter');
		$this->sFeatureSlug = 'comments_filter';
		parent::__construct( $oPluginVo, 'commentsfilter' ); //TODO: align this naming with the feature slug etc. as with the other features.
	}

	/**
	 * @return ICWP_WPSF_Processor_CommentsFilter|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().'icwp-processor-commentsfilter.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_CommentsFilter( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {

		$aBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('SPAM Comments Protection Filter') ),
			'section_options' => array(
				array(
					'enable_comments_filter',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Comments Filter' ),
					_wpsf__( 'Enable (or Disable) The SPAM Comments Protection Filter Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('SPAM Comments Protection Filter') ),
					'<a href="http://icwp.io/3z" target="_blank">'._wpsf__( 'more info' ).'</a>'
					.' | <a href="http://icwp.io/wpsf04" target="_blank">'._wpsf__( 'blog' ).'</a>'
				)
			)
		);

		$aHumanSpam = array(
			'section_title' => sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Human') ),
			'section_options' => array(
				array(
					'enable_comments_human_spam_filter',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Human SPAM Filter' ),
					_wpsf__( 'Enable (or Disable) The Human SPAM Filter Feature.' ),
					_wpsf__( 'Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below.' ),
					'<a href="http://icwp.io/57" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_comments_human_spam_filter_items',
					'',
					$this->getHumanSpamFilterItems( true ),
					$this->getHumanSpamFilterItems(),
					_wpsf__( 'Comment Filter Items' ),
					_wpsf__( 'Select The Items To Scan For SPAM' ),
					_wpsf__( 'When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content.' ).' '.sprintf( _wpsf__('Recommended: %s'), _wpsf__('All') ),
					'<a href="http://icwp.io/58" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'comments_default_action_human_spam',
					'',
					'spam',
					$this->getSpamHandlingResponses(),
					_wpsf__( 'Default SPAM Action' ),
					_wpsf__( 'How To Categorise Comments When Identified To Be SPAM' ),
					sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('a human commenter').'</span>' ),
					'<a href="http://icwp.io/59" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			),
		);

		$aGasp = array(
			'section_title' => sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Automatic Bot') ),
			'section_options' => array(
				array(
					'enable_comments_gasp_protection',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'GASP Protection' ),
					_wpsf__( 'Add Growmap Anti Spambot Protection to your comments' ),
					_wpsf__( 'Taking the lead from the original GASP plugin for WordPress, we have extended it to include advanced spam-bot protection.' ),
					'<a href="http://icwp.io/3n" target="_blank">'._wpsf__( 'more info' ).'</a>'
						.' | <a href="http://icwp.io/2n" target="_blank">'._wpsf__( 'blog' ).'</a>'
				),
				array(
					'comments_default_action_spam_bot',
					'',
					'trash',
					$this->getSpamHandlingResponses(),
					_wpsf__( 'Default SPAM Action' ),
					_wpsf__( 'How To Categorise Comments When Identified To Be SPAM' ),
					sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('an automatic bot').'</span>' ),
					'<a href="http://icwp.io/59" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_comments_gasp_protection_for_logged_in',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Include Logged-In Users' ),
					_wpsf__( 'You may also enable GASP for logged in users' ),
					_wpsf__( 'Since logged-in users would be expected to be vetted already, this is off by default.' )
				),
				array(
					'comments_cooldown_interval',
					'',
					'30',
					'integer',
					_wpsf__( 'Comments Cooldown' ),
					_wpsf__( 'Limit posting comments to X seconds after the page has loaded' ),
					_wpsf__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts." ),
					'<a href="http://icwp.io/3o" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'comments_token_expire_interval',
					'',
					'600',
					'integer',
					_wpsf__( 'Comment Token Expire' ),
					_wpsf__( 'A visitor has X seconds within which to post a comment' ),
					_wpsf__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors." ),
					'<a href="http://icwp.io/3o" target="_blank">'._wpsf__( 'more info' ).'</a>'

				)
			)
		);

		$aCustomMessages = array(
			'section_title' => sprintf( _wpsf__( 'Customize Messages Shown To User' ), _wpsf__('Automatic Bot') ),
			'section_options' => array(
				array(
					'custom_message_checkbox',
					'',
					_wpsf__( "I'm not a spammer" ),
					'text',
					_wpsf__( 'Custom Checkbox Message' ),
					_wpsf__( 'If you want a custom checkbox message, please provide this here' ),
					_wpsf__( "You can customise the message beside the checkbox." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'custom_message_alert',
					'',
					_wpsf__( "Please check the box to confirm you're not a spammer" ),
					'text',
					_wpsf__( 'Custom Alert Message' ),
					_wpsf__( 'If you want a custom alert message, please provide this here' ),
					_wpsf__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'custom_message_comment_wait',
					'',
					_wpsf__( "Please wait %s seconds before posting your comment" ),
					'text',
					_wpsf__( 'Custom Wait Message' ),
					_wpsf__( 'If you want a custom submit-button wait message, please provide this here.' ),
					_wpsf__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these." )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__('Please wait %s seconds before posting your comment') ),
					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'custom_message_comment_reload',
					'',
					_wpsf__( "Please reload this page to post a comment" ),
					'text',
					_wpsf__( 'Custom Reload Message' ),
					_wpsf__( 'If you want a custom message when the comment token has expired, please provide this here.' ),
					_wpsf__( 'This message is displayed on the submit-button when the comment token is expired' )
						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please reload this page to post a comment") ),
					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);

		$aOptionsDefinitions = array(
			$aBase,
			$aHumanSpam,
			$aGasp,
			$aCustomMessages
		);
		return $aOptionsDefinitions;
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

		$aCommentsFilters = $this->getOpt( 'enable_comments_human_spam_filter_items' );
		if ( empty($aCommentsFilters) || !is_array( $aCommentsFilters ) ) {
			$this->setOpt( 'enable_comments_human_spam_filter_items', $this->getHumanSpamFilterItems( true ) );
		}
	}

	/**
	 * @return array
	 */
	protected function getSpamHandlingResponses() {
		return array( 'select',
			array( 0, 				_wpsf__( 'Mark As Pending Moderation' ) ),
			array( 'spam', 			_wpsf__( 'Mark As SPAM' ) ),
			array( 'trash', 		_wpsf__( 'Move To Trash' ) ),
			array( 'reject', 		_wpsf__( 'Reject And Redirect' ) )
		);
	}

	/**
	 *
	 */
	protected function getHumanSpamFilterItems( $fAsDefaults = false ) {
		$aFilterItems = array( 'type' => 'multiple_select',
			'author_name'		=> _wpsf__('Author Name'),
			'author_email'		=> _wpsf__('Author Email'),
			'comment_content'	=> _wpsf__('Comment Content'),
			'url'				=> _wpsf__('URL'),
			'ip_address'		=> _wpsf__('IP Address'),
			'user_agent'		=> _wpsf__('Browser User Agent')
		);
		if ( $fAsDefaults ) {
			unset($aFilterItems['type']);
			return array_keys($aFilterItems);
		}
		return $aFilterItems;
	}
}

endif;