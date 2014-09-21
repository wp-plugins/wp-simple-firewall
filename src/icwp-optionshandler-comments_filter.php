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
			require_once( $this->oPluginVo->getSourceDir().sprintf( 'icwp-processor-%s.php', $this->getFeatureSlug() ) );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_CommentsFilter( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_enable_plugin_feature_spam_comments_protection_filter' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('SPAM Comments Protection Filter') );
				break;

			case 'section_enable_human_comment_spam_protection_filter' :
				$sTitle = sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Human') );
				break;

			case 'section_enable_automatic_bot_comment_spam_protection_filter' :
				$sTitle = sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Automatic Bot') );
				break;

			case 'section_customize_messages_shown_to_user' :
				$sTitle = _wpsf__( 'Customize Messages Shown To User' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams['section_title'] = $sTitle;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams['key'];
		switch( $sKey ) {

			case 'enable_comments_filter' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Comments Filter') );
				$sSummary = _wpsf__( 'Enable (or Disable) The SPAM Comments Protection Filter Feature' );
				$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('SPAM Comments Protection Filter') );
				break;

			case 'enable_comments_human_spam_filter' :
				$sName = _wpsf__( 'Human SPAM Filter' );
				$sSummary = _wpsf__( 'Enable (or Disable) The Human SPAM Filter Feature.' );
				$sDescription = _wpsf__( 'Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below.' );
				break;

			case 'enable_comments_human_spam_filter_items' :
				$sName = _wpsf__( 'Comment Filter Items' );
				$sSummary = _wpsf__( 'Select The Items To Scan For SPAM' );
				$sDescription = _wpsf__( 'When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content.' ).' '.sprintf( _wpsf__('Recommended: %s'), _wpsf__('All') );
				break;

			case 'comments_default_action_human_spam' :
				$sName = _wpsf__( 'Default SPAM Action' );
				$sSummary = _wpsf__( 'How To Categorise Comments When Identified To Be SPAM' );
				$sDescription = sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('a human commenter').'</span>' );
				break;

			case 'enable_comments_gasp_protection' :
				$sName = _wpsf__( 'GASP Protection' );
				$sSummary = _wpsf__( 'Add Growmap Anti Spambot Protection to your comments' );
				$sDescription = _wpsf__( 'Taking the lead from the original GASP plugin for WordPress, we have extended it to include advanced spam-bot protection.' );
				break;

			case 'comments_default_action_spam_bot' :
				$sName = _wpsf__( 'Default SPAM Action' );
				$sSummary = _wpsf__( 'How To Categorise Comments When Identified To Be SPAM' );
				$sDescription = sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('an automatic bot').'</span>' );
				break;

			case 'enable_comments_gasp_protection_for_logged_in' :
				$sName = _wpsf__( 'Include Logged-In Users' );
				$sSummary = _wpsf__( 'You may also enable GASP for logged in users' );
				$sDescription = _wpsf__( 'Since logged-in users would be expected to be vetted already, this is off by default.' );
				break;

			case 'comments_cooldown_interval' :
				$sName = _wpsf__( 'Comments Cooldown' );
				$sSummary = _wpsf__( 'Limit posting comments to X seconds after the page has loaded' );
				$sDescription = _wpsf__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post multiple times to your posts." );
				break;

			case 'comments_token_expire_interval' :
				$sName = _wpsf__( 'Comment Token Expire' );
				$sSummary = _wpsf__( 'A visitor has X seconds within which to post a comment' );
				$sDescription = _wpsf__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors." );
				break;

			case 'custom_message_checkbox' :
				$sName = _wpsf__( 'Custom Checkbox Message' );
				$sSummary = _wpsf__( 'If you want a custom checkbox message, please provide this here' );
				$sDescription = _wpsf__( "You can customise the message beside the checkbox." )
					.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") );
				break;

			case 'custom_message_alert' :
				$sName = _wpsf__( 'Custom Alert Message' );
				$sSummary = _wpsf__( 'If you want a custom alert message, please provide this here' );
				$sDescription = _wpsf__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box." )
					.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") );
				break;

			case 'custom_message_comment_wait' :
				$sName = _wpsf__( 'Custom Wait Message' );
				$sSummary = _wpsf__( 'If you want a custom submit-button wait message, please provide this here.' );
				$sDescription = _wpsf__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these." )
					.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__('Please wait %s seconds before posting your comment') );
				break;

			case 'custom_message_comment_reload' :
				$sName = _wpsf__( 'Custom Reload Message' );
				$sSummary = _wpsf__( 'If you want a custom message when the comment token has expired, please provide this here.' );
				$sDescription = _wpsf__( 'This message is displayed on the submit-button when the comment token is expired' )
					.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please reload this page to post a comment") );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams['name'] = $sName;
		$aOptionsParams['summary'] = $sSummary;
		$aOptionsParams['description'] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {

//		$aBase = array(
//			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('SPAM Comments Protection Filter') ),
//			'section_options' => array(
//				array(
//					'enable_comments_filter',
//					'',
//					'N',
//					'checkbox',
//					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Comments Filter') ),
//					_wpsf__( 'Enable (or Disable) The SPAM Comments Protection Filter Feature' ),
//					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('SPAM Comments Protection Filter') ),
//					'<a href="http://icwp.io/3z" target="_blank">'._wpsf__( 'more info' ).'</a>'
//					.' | <a href="http://icwp.io/wpsf04" target="_blank">'._wpsf__( 'blog' ).'</a>'
//				)
//			)
//		);
//
//		$aHumanSpam = array(
//			'section_title' => sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Human') ),
//			'section_options' => array(
//				array(
//					'enable_comments_human_spam_filter',
//					'',
//					'N',
//					'checkbox',
//					_wpsf__( 'Human SPAM Filter' ),
//					_wpsf__( 'Enable (or Disable) The Human SPAM Filter Feature.' ),
//					_wpsf__( 'Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below.' ),
//					'<a href="http://icwp.io/57" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'enable_comments_human_spam_filter_items',
//					'',
//					$this->getHumanSpamFilterItems( true ),
//					$this->getHumanSpamFilterItems(),
//					_wpsf__( 'Comment Filter Items' ),
//					_wpsf__( 'Select The Items To Scan For SPAM' ),
//					_wpsf__( 'When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content.' ).' '.sprintf( _wpsf__('Recommended: %s'), _wpsf__('All') ),
//					'<a href="http://icwp.io/58" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'comments_default_action_human_spam',
//					'',
//					'spam',
//					$this->getSpamHandlingResponses(),
//					_wpsf__( 'Default SPAM Action' ),
//					_wpsf__( 'How To Categorise Comments When Identified To Be SPAM' ),
//					sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('a human commenter').'</span>' ),
//					'<a href="http://icwp.io/59" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				)
//			),
//		);
//
//		$aGasp = array(
//			'section_title' => sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__('Automatic Bot') ),
//			'section_options' => array(
//				array(
//					'enable_comments_gasp_protection',
//					'',
//					'Y',
//					'checkbox',
//					_wpsf__( 'GASP Protection' ),
//					_wpsf__( 'Add Growmap Anti Spambot Protection to your comments' ),
//					_wpsf__( 'Taking the lead from the original GASP plugin for WordPress, we have extended it to include advanced spam-bot protection.' ),
//					'<a href="http://icwp.io/3n" target="_blank">'._wpsf__( 'more info' ).'</a>'
//						.' | <a href="http://icwp.io/2n" target="_blank">'._wpsf__( 'blog' ).'</a>'
//				),
//				array(
//					'comments_default_action_spam_bot',
//					'',
//					'trash',
//					$this->getSpamHandlingResponses(),
//					_wpsf__( 'Default SPAM Action' ),
//					_wpsf__( 'How To Categorise Comments When Identified To Be SPAM' ),
//					sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__('an automatic bot').'</span>' ),
//					'<a href="http://icwp.io/59" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'enable_comments_gasp_protection_for_logged_in',
//					'',
//					'N',
//					'checkbox',
//					_wpsf__( 'Include Logged-In Users' ),
//					_wpsf__( 'You may also enable GASP for logged in users' ),
//					_wpsf__( 'Since logged-in users would be expected to be vetted already, this is off by default.' )
//				),
//				array(
//					'comments_cooldown_interval',
//					'',
//					'30',
//					'integer',
//					_wpsf__( 'Comments Cooldown' ),
//					_wpsf__( 'Limit posting comments to X seconds after the page has loaded' ),
//					_wpsf__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post mutliple times to your posts." ),
//					'<a href="http://icwp.io/3o" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'comments_token_expire_interval',
//					'',
//					'600',
//					'integer',
//					_wpsf__( 'Comment Token Expire' ),
//					_wpsf__( 'A visitor has X seconds within which to post a comment' ),
//					_wpsf__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors." ),
//					'<a href="http://icwp.io/3o" target="_blank">'._wpsf__( 'more info' ).'</a>'
//
//				)
//			)
//		);
//
//		$aCustomMessages = array(
//			'section_title' => sprintf( _wpsf__( 'Customize Messages Shown To User' ), _wpsf__('Automatic Bot') ),
//			'section_options' => array(
//				array(
//					'custom_message_checkbox',
//					'',
//					_wpsf__( "I'm not a spammer" ),
//					'text',
//					_wpsf__( 'Custom Checkbox Message' ),
//					_wpsf__( 'If you want a custom checkbox message, please provide this here' ),
//					_wpsf__( "You can customise the message beside the checkbox." )
//						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
//					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'custom_message_alert',
//					'',
//					_wpsf__( "Please check the box to confirm you're not a spammer" ),
//					'text',
//					_wpsf__( 'Custom Alert Message' ),
//					_wpsf__( 'If you want a custom alert message, please provide this here' ),
//					_wpsf__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box." )
//						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please check the box to confirm you're not a spammer") ),
//					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'custom_message_comment_wait',
//					'',
//					_wpsf__( "Please wait %s seconds before posting your comment" ),
//					'text',
//					_wpsf__( 'Custom Wait Message' ),
//					_wpsf__( 'If you want a custom submit-button wait message, please provide this here.' ),
//					_wpsf__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these." )
//						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__('Please wait %s seconds before posting your comment') ),
//					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
//				array(
//					'custom_message_comment_reload',
//					'',
//					_wpsf__( "Please reload this page to post a comment" ),
//					'text',
//					_wpsf__( 'Custom Reload Message' ),
//					_wpsf__( 'If you want a custom message when the comment token has expired, please provide this here.' ),
//					_wpsf__( 'This message is displayed on the submit-button when the comment token is expired' )
//						.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__("Please reload this page to post a comment") ),
//					'<a href="http://icwp.io/3p" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				)
//			)
//		);
//
//		$aOptionsDefinitions = array(
//			$aBase,
//			$aHumanSpam,
//			$aGasp,
//			$aCustomMessages
//		);
//		return $aOptionsDefinitions;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		if ( $this->getOpt( 'comments_cooldown_interval' ) < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'comments_cooldown_interval' );
		}
		
		if ( $this->getOpt( 'comments_token_expire_interval' ) < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'comments_token_expire_interval' );
		}
		
		if ( $this->getOpt( 'comments_token_expire_interval' ) != 0 && $this->getOpt( 'comments_cooldown_interval' ) > $this->getOpt( 'comments_token_expire_interval' ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'comments_cooldown_interval' );
			$this->getOptionsVo()->resetOptToDefault( 'comments_token_expire_interval' );
		}

		$aCommentsFilters = $this->getOpt( 'enable_comments_human_spam_filter_items' );
		if ( empty( $aCommentsFilters ) || !is_array( $aCommentsFilters ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'enable_comments_human_spam_filter_items' );
		}
	}

	/**
	 * @return string
	 */
	public function getCommentsFilterTableName() {
		return $this->doPluginPrefix( $this->getOpt( 'spambot_comments_filter_table_name' ), '_' );
	}
}

endif;